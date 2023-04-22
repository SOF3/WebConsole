<?php

declare(strict_types=1);

namespace libs\_cb07bb7a956d14fd\SOFe\WebConsole\Lib;

use Closure;
use Generator;
use pocketmine\event\Event;
use pocketmine\plugin\Plugin;
use libs\_cb07bb7a956d14fd\SOFe\AwaitGenerator\Channel;
use libs\_cb07bb7a956d14fd\SOFe\AwaitGenerator\Traverser;
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