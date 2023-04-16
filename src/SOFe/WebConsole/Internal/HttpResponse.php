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
use libs\_85f6d346dd7f97fb\SOFe\AwaitGenerator\Await;
use libs\_85f6d346dd7f97fb\SOFe\AwaitGenerator\Traverser;
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
























































































































































































































































































































































































final class HttpResponse {
    /**
     * @param Traverser<string> $body
     */
    public function __construct(
        public string $httpVersion,
        public string $httpCode,
        public HttpHeaders $headers,
        public Traverser $body,
    ) {
    }
}