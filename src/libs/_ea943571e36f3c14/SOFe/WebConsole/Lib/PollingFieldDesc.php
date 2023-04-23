<?php

declare(strict_types=1);

namespace libs\_ea943571e36f3c14\SOFe\WebConsole\Lib;

use Closure;
use Generator;
use pocketmine\event\Event;
use pocketmine\plugin\Plugin;
use libs\_ea943571e36f3c14\SOFe\AwaitGenerator\Channel;
use libs\_ea943571e36f3c14\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\FieldDesc;








































































/**
 * @template I
 * @template V
 * @implements FieldDesc<I, V>
 */
final class PollingFieldDesc implements FieldDesc {
    /**
     * @param Closure(I): Generator<mixed, mixed, mixed, V> $getter
     */
    public function __construct(
        private Plugin $plugin,
        private Closure $getter,
        private int $periodTicks,
    ) {
    }

    public function get($object) : Generator {
        return ($this->getter)($object);
    }

    public function watch($object) : Traverser {
        return Traverser::fromClosure(function() use ($object) {
            while (true) {
                $value = yield from ($this->getter)($object);
                yield $value => Traverser::VALUE;

                yield from Util::sleep($this->plugin, $this->periodTicks);
            }
        });
    }
}