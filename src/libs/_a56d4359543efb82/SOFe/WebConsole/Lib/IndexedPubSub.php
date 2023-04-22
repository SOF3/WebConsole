<?php

declare(strict_types=1);

namespace libs\_a56d4359543efb82\SOFe\WebConsole\Lib;

use AssertionError;
use Closure;
use Generator;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use libs\_a56d4359543efb82\SOFe\AwaitGenerator\Await;
use libs\_a56d4359543efb82\SOFe\AwaitGenerator\Channel;
use libs\_a56d4359543efb82\SOFe\AwaitGenerator\PubSub;
use libs\_a56d4359543efb82\SOFe\AwaitGenerator\Traverser;


















































/**
 * @template T
 */
final class IndexedPubSub {
    /** @var PubSub<T>[] */
    private array $topics = [];

    /**
     * @return Generator<mixed, mixed, mixed, T>
     */
    public function watchOnce(string $key) : Generator {
        $this->topics[$key] ??= new PubSub();

        $sub = $this->topics[$key]->subscribe();
        $ok = yield from $sub->next($item);
        if (!$ok) {
            throw new AssertionError("pubsub subscriber never returns gracefully");
        }

        yield from $sub->interrupt();
        if ($this->topics[$key]->isEmpty()) {
            unset($this->topics[$key]);
        }

        return $item;
    }

    /**
     * @return Traverser<T>
     */
    public function watchContinuous(string $key) : Traverser {
        return Traverser::fromClosure(function() use ($key) {
            $sub = $this->topics[$key]->subscribe();

            try {
                while (yield from $sub->next($item)) {
                    yield $item => Traverser::VALUE;
                }

                throw new AssertionError("pubsub subscriber never returns gracefully");
            } finally {
                yield from $sub->interrupt();

                if ($this->topics[$key]->isEmpty()) {
                    unset($this->topics[$key]);
                }
            }
        });
    }

    /**
     * @param T $item
     */
    public function publish(string $key, $item) : void {
        if (!isset($this->topics[$key])) {
            return;
        }

        $this->topics[$key]->publish($item);
    }
}