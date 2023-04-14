<?php

declare(strict_types=1);

namespace libs\_734bfd15e9991e6b\SOFe\WebConsole\Lib;

use Closure;
use Generator;
use pocketmine\event\Event;
use pocketmine\plugin\Plugin;
use libs\_734bfd15e9991e6b\SOFe\AwaitGenerator\Channel;
use libs\_734bfd15e9991e6b\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\FieldDesc;

/**
 * @template I
 * @template V
 * @implements FieldDesc<I, V>
 */
final class EventBasedFieldDesc implements FieldDesc {
    /**
     * @template E of Event
     * @param list<class-string<E>> $events
     * @param Closure(I): Generator<mixed, mixed, mixed, V> $getter
     * @param Closure(E, I): bool $testEvent whether the event affects the object
     */
    public function __construct(
        private Plugin $plugin,
        private array $events,
        private Closure $getter,
        private Closure $testEvent,
    ) {
    }

    public function get($object) : Generator {
        return ($this->getter)($object);
    }

    public function watch($object) : Traverser {
        return Traverser::fromClosure(function() use ($object) {
            $previous = yield from ($this->getter)($object);
            yield $previous => Traverser::VALUE;

            yield from Util::withListener($this->plugin, $this->events, function(Channel $channel) use ($object, &$previous) {
                while (true) {
                    $event = yield from $channel->receive();
                    if (($this->testEvent)($event, $object)) {
                        $value = yield from ($this->getter)($object);
                        if ($value !== $previous) {
                            $previous = $value;
                            yield $value => Traverser::VALUE;
                        }
                    }
                }
            });
        });
    }
}