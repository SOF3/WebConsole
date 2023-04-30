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
use libs\_37f8d49eb6299cb1\SOFe\AwaitGenerator\Await;
use libs\_37f8d49eb6299cb1\SOFe\AwaitGenerator\Traverser;
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




























































































































































































































































































































































final class HttpHeaders {
    /**
     * @param array<string, string> $headers
     */
    public function __construct(public array $headers = []) {
        $this->headers["Connection"] = "close";
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