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
use libs\_734bfd15e9991e6b\SOFe\AwaitGenerator\Await;
use libs\_734bfd15e9991e6b\SOFe\AwaitGenerator\Traverser;
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































































































































































































































































































































final class HttpAddress {
    /**
     * @param string $method always uppercase
     * @param string $path always starts with a slash
     * @param array<string, string[]> $query GET parameters of the request
     * @param string $httpVersion must start with "HTTP/1"
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public string $httpVersion,
    ) {
    }

    /**
     * @template T
     * @param T $default
     * @return string|T
     */
    public function getQueryOnce(string $key, $default) {
        if (isset($this->query[$key][0])) {
            return $this->query[$key][0];
        }

        return $default;
    }
}