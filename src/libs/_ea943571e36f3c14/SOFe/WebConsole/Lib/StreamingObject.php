<?php

declare(strict_types=1);

namespace libs\_ea943571e36f3c14\SOFe\WebConsole\Lib;

use AssertionError;
use Generator;
use InvalidArgumentException;
use libs\_ea943571e36f3c14\SOFe\AwaitGenerator\PubSub;
use libs\_ea943571e36f3c14\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\ObjectDesc;
use function array_key_first;
use function array_slice;
use function bcadd;
use function count;
use function dechex;
use function str_pad;
use function strlen;
















































































































/**
 * @template I
 */
final class StreamingObject {
    /**
     * @param I $object
     */
    public function __construct(
        public string $name,
        public $object,
    ) {
    }
}