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
use libs\_1ef0ff44a3bf42da\SOFe\AwaitGenerator\Await;
use libs\_1ef0ff44a3bf42da\SOFe\AwaitGenerator\Traverser;
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