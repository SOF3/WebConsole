<?php

declare(strict_types=1);

namespace libs\_cb07bb7a956d14fd\SOFe\WebConsole\Lib;

use AssertionError;
use Generator;
use InvalidArgumentException;
use libs\_cb07bb7a956d14fd\SOFe\AwaitGenerator\PubSub;
use libs\_cb07bb7a956d14fd\SOFe\AwaitGenerator\Traverser;
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