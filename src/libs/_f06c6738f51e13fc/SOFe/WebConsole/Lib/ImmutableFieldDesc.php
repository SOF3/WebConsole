<?php

declare(strict_types=1);

namespace libs\_f06c6738f51e13fc\SOFe\WebConsole\Lib;

use Closure;
use Generator;
use pocketmine\event\Event;
use pocketmine\plugin\Plugin;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Channel;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\FieldDesc;














































/**
 * @template I
 * @template V
 * @implements FieldDesc<I, V>
 */
final class ImmutableFieldDesc implements FieldDesc {
    /**
     * @param Closure(I): Generator<mixed, mixed, mixed, V> $getter
     */
    public function __construct(
        private Closure $getter,
    ) {
    }

    public function get($object) : Generator {
        return ($this->getter)($object);
    }

    public function watch($object) : Traverser {
        return Traverser::fromClosure(function() use ($object) {
            $value = yield from ($this->getter)($object);
            yield $value => Traverser::VALUE;
        });
    }
}