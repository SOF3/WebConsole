<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Internal;

use Closure;
use ErrorException;
use Exception;
use Generator;
use Logger;
use PrefixedLogger;
use Socket;
use libs\_fda4469118c2f4f9\SOFe\AwaitGenerator\Await;
use libs\_fda4469118c2f4f9\SOFe\AwaitGenerator\Traverser;
use function count;
use function explode;
use function is_array;
use function microtime;
use function parse_str;
use function socket_accept;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_getpeername;
use function socket_last_error;
use function socket_listen;
use function socket_read;
use function socket_set_nonblock;
use function socket_set_option;
use function socket_strerror;
use function socket_write;
use function strlen;
use function strpos;
use function strtolower;
use function strtoupper;
use function substr;
use function urldecode;


















































































final class HttpClient {
    private bool $closed = false;
    private ?Closure $nextOperation = null;
    private StreamReader $reader;

    /**
     * @param Closure(HttpRequest $request): Generator<mixed, mixed, mixed, HttpResponse> $handler
     */
    public function __construct(
        private Logger $logger,
        private Socket $socket,
        int $timeout,
        int $maxRequestSize,
        Closure $handler,
    ) {
        $this->reader = new StreamReader(function() use ($timeout) {
            $lastData = microtime(true);

            return yield from $this->retry(function() use (&$lastData, $timeout) {
                if (microtime(true) - $lastData > $timeout) {
                    throw new HttpException("No data for $timeout seconds");
                }

                $buffer = socket_read($this->socket, 4096);
                if ($buffer === false) {
                    $err = socket_last_error($this->socket);
                    if ($err === SOCKET_EAGAIN || $err === SOCKET_EWOULDBLOCK) {
                        return ["", true];
                    }

                    throw $this->throw("read socket");
                }

                $lastData = microtime(true);
                return [$buffer, false];
            });
        }, $maxRequestSize);

        Await::f2c(function() use ($handler) {
            try {
                yield from $this->run($handler);
            } catch(HttpException|ErrorException $e) {
                $this->close();
                $this->logger->debug($e->getMessage());
            }
        });
    }

    /**
     * @param Closure(HttpRequest $request): Generator<mixed, mixed, mixed, HttpResponse> $handler
     */
    private function run(Closure $handler) : Generator {
        if (!socket_getpeername($this->socket, $clientIp, $clientPort)) {
            throw $this->throw("get socket peer name");
        }

        if (!socket_set_nonblock($this->socket)) {
            throw $this->throw("set socket as nonblocking");
        }

        $addressLine = yield from $this->reader->readLine();
        $address = $this->parseAddressLine($addressLine);

        $headers = new HttpHeaders;
        while (true) {
            $headerLine = yield from $this->reader->readLine();
            if (!$headers->parse($headerLine)) {
                break;
            }
        }

        $body = "";
        if (isset($headers->headers["content-length"])) {
            $length = (int) $headers->headers["content-length"];

            $body = yield from $this->reader->readBinary($length);
        }

        $request = new HttpRequest($clientIp, $clientPort, $address, $headers, $body);

        $this->logger->debug("Received request {$address->method} {$address->path} from $clientIp");

        /** @var HttpResponse $response */
        $response = yield from $handler($request);

        $this->write("$response->httpVersion $response->httpCode\r\n");
        foreach ($response->headers->headers as $key => $value) {
            $this->write("$key: $value\r\n");
        }
        $this->write("\r\n");

        while (yield from $response->body->next($buffer)) {
            $this->write($buffer);
        }

        $this->close();
    }

    private function write(string $buffer) : void {
        if (socket_write($this->socket, $buffer) === false) {
            throw $this->throw("write response");
        }
    }

    private function parseAddressLine(string $line) : HttpAddress {
        $parts = explode(" ", $line, 3);
        if (count($parts) !== 3) {
            throw new HttpException("Protocol error: invalid address line");
        }

        [$method, $path, $httpVersion] = $parts;
        $method = strtoupper($method);
        if ($path[0] !== "/" || strpos($httpVersion, "HTTP/1") !== 0) {
            throw new HttpException("Protocol error: invalid address line");
        }

        /** @var array<string, list<string>> */
        $query = [];
        $pos = strpos($path, "?");
        if ($pos !== false) {
            parse_str(substr($path, $pos + 1), $queryTmp);
            foreach ($queryTmp as &$values) {
                if (!is_array($values)) {
                    $values = [$values];
                }
            }
            unset($values);
            /** @var array<string, list<string>> $queryTmp */
            $query = $queryTmp;

            $path = substr($path, 0, $pos);
        }
        $path = urldecode($path);

        return new HttpAddress($method, $path, $query, $httpVersion);
    }

    /**
     * @template T
     * @param Closure(): array{T, bool} $operation
     * @return Generator<mixed, mixed, mixed, T>
     */
    private function retry(Closure $operation) : Generator {
        return Await::promise(function(Closure $resolve) use ($operation) {
            $this->nextOperation = function() use ($operation, $resolve) : void {
                [$output, $retry] = $operation();
                if (!$retry) {
                    $this->nextOperation = null;
                    $resolve($output);
                }
            };
        });
    }

    public function tick() : void {
        if ($this->nextOperation !== null) {
            ($this->nextOperation)();
        }
    }

    private function throw(string $operation) : HttpException {
        throw new HttpException("Failed to $operation: " . socket_strerror(socket_last_error($this->socket)));
    }

    public function close() : void {
        $this->closed = true;
        socket_close($this->socket);
    }

    public function isClosed() : bool {
        return $this->closed;
    }
}