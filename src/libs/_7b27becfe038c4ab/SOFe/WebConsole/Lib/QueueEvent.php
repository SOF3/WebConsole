<?php

declare(strict_types=1);

namespace libs\_7b27becfe038c4ab\SOFe\WebConsole\Lib;

use AssertionError;
use Generator;
use InvalidArgumentException;
use libs\_7b27becfe038c4ab\SOFe\AwaitGenerator\PubSub;
use libs\_7b27becfe038c4ab\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\ObjectDesc;
use function array_key_first;
use function array_slice;
use function bin2hex;
use function count;
use function random_bytes;
use function random_int;



















































































































/**
 * @template I
 */
final class QueueEvent {
    /**
     * @param I $object
     */
    public function __construct(
        public bool $add,
        public string $name,
        public $object,
    ) {
    }
}