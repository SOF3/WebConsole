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
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Await;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Traverser;
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
            if ($client->isClosed()) {
                unset($this->clients[$sessionId]);
            }

            try {
                $client->tick();
            } catch (HttpException|ErrorException $e) {
                $client->close();
                $this->logger->debug($e->getMessage());
            }

            if ($client->isClosed()) {
                unset($this->clients[$sessionId]);
            }
        }
    }
}