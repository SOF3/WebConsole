<?php

declare(strict_types=1);

namespace SOFe\WebConsole;

use Closure;
use Exception;
use Generator;
use Logger;
use PrefixedLogger;
use Socket;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Traverser;
use function count;
use function explode;
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

final class HttpServer {
    private Socket $socket;
    /** @var array<HttpClient> */
    private array $clients = [];
    private int $nextSessionId = 0;

    /**
     * @param Closure(HttpRequest $request): Generator<mixed, mixed, mixed, HttpResponse> $handler
     */
    public function __construct(
        private Logger $logger,
        private string $address,
        private int $port,
        private int $timeout,
        private int $maxRequestSize,
        private Closure $handler,
    ) {
    }

    public function listen() : void {
        $sk = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$sk) {
            throw new HttpException("Failed to create socket: " . socket_strerror(socket_last_error())); // socket is not initialized yet
        }

        $this->socket = $sk;

        if (!socket_set_option($sk, SOL_SOCKET, SO_REUSEADDR, 1)) {
            throw $this->throw("set socket to REUSEADDR");
        }

        if (!socket_set_nonblock($sk)) {
            throw $this->throw("set socket to non-blocking mode");
        }

        if (!socket_bind($sk, $this->address, $this->port)) {
            throw $this->throw("bind socket");
        }

        if (!socket_listen($sk)) {
            throw $this->throw("listen to socket");
        }

        $this->logger->info("Listening on {$this->address}:{$this->port}");
    }

    private function throw(string $operation) : HttpException {
        throw new HttpException("Failed to $operation: " . socket_strerror(socket_last_error($this->socket)));
    }

    public function tick() : void {
        $sk = socket_accept($this->socket);
        if ($sk === false && socket_last_error($this->socket) !== 0) {
            throw $this->throw("accept socket");
        }

        if ($sk !== false) {
            $sessionId = $this->nextSessionId++;
            $logger = new PrefixedLogger($this->logger, "Session #$sessionId");
            $this->clients[$sessionId] = new HttpClient($logger, $sk, $this->timeout, $this->maxRequestSize, $this->handler);
        }

        foreach ($this->clients as $sessionId => $client) {
            try {
                $client->tick();
            } catch (HttpException $e) {
                // DoS protection, do not log the error
                $client->close();
            }

            if ($client->isClosed()) {
                unset($this->clients[$sessionId]);
            }
        }
    }
}

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

                    throw $this->throw("socket read");
                }

                $lastData = microtime(true);
                return [$buffer, false];
            });
        }, $maxRequestSize);

        Await::g2c($this->run($handler));
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
            throw $this->throw("writing response");
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

        $pos = strpos($path, "?");
        if ($pos !== false) {
            parse_str(substr($path, $pos + 1), $query);
            $path = substr($path, 0, $pos);
        } else {
            $query = [];
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

final class StreamReader {
    private string $buffer = "";
    private int $offset = 0;

    /**
     * @param Closure(): Generator<mixed, mixed, mixed, string> $reader
     */
    public function __construct(private Closure $reader, private int $maxRequestSize) {
    }

    /**
     * @return Generator<mixed, mixed, mixed, string>
     */
    public function readLine() : Generator {
        while (true) {
            $pos = strpos($this->buffer, "\r\n", $this->offset);
            if ($pos === false) {
                yield from $this->flush();
                continue;
            }

            $line = substr($this->buffer, $this->offset, $pos - $this->offset);
            $this->offset = $pos + 2;
            return $line;
        }
    }

    public function readBinary(int $length) : Generator {
        while (strlen($this->buffer) - $this->offset < $length) {
            yield from $this->flush();
        }

        $buffer = substr($this->buffer, $this->offset, $length);
        return $buffer;
    }

    private function flush() : Generator {
        $buffer = yield from ($this->reader)();
        $this->maxRequestSize -= strlen($buffer);
        if ($this->maxRequestSize < 0) {
            throw new HttpException("Request is too large");
        }

        if ($this->offset === 0) {
            $this->buffer .= $buffer;
        } else {
            $this->buffer = substr($this->buffer, $this->offset) . $buffer;
            $this->offset = 0;
        }
    }
}

final class HttpRequest {
    public function __construct(
        public string $clientIp,
        public int $clientPort,
        public HttpAddress $address,
        public HttpHeaders $headers,
        public string $body,
    ) {
    }
}

final class HttpAddress {
    /**
     * @param string $method always uppercase
     * @param string $path always starts with a slash
     * @param array<string, string|string[]> $query GET parameters of the request
     * @param string $httpVersion must start with "HTTP/1"
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public string $httpVersion,
    ) {
    }
}

final class HttpHeaders {
    /**
     * @param array<string, string> $headers
     */
    public function __construct(public array $headers = []) {
        $this->headers["Server"] = "PocketMine-MP WebConsole";
        $this->headers["Access-Control-Allow-Origin"] = "*";
    }

    public function parse(string $line) : bool {
        if ($line === "") {
            return false;
        }

        $pos = strpos($line, ": ");
        if ($pos === false) {
            throw new HttpException("Protocol error: header line does not have colon");
        }

        $key = substr($line, 0, $pos);
        $value = substr($line, $pos + 2);

        $this->headers[strtolower($key)] = $value;
        return true;
    }
}

final class HttpResponse {
    public function __construct(
        public string $httpVersion,
        public string $httpCode,
        public HttpHeaders $headers,
        public Traverser $body,
    ) {
    }
}

final class HttpException extends Exception {
}
