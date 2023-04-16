<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Lib;

use AssertionError;
use Closure;
use Generator;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitGenerator\PubSub;
use SOFe\AwaitGenerator\Traverser;

final class Util {
    /**
     * @template E of Event
     * @template R
     * @param list<class-string<E>> $classes
     * @param Closure(Channel<E>): Generator<mixed, mixed, mixed, R> $run
     * @return Generator<mixed, mixed, mixed, R>
     */
    public static function withListener(Plugin $plugin, array $classes, Closure $run) : Generator {
        $channel = new Channel;

        $listeners = [];
        foreach ($classes as $class) {
            $listeners[] = [$class, Server::getInstance()->getPluginManager()->registerEvent(
                event: $class,
                handler: fn($event) => $channel->sendWithoutWait($event),
                priority: EventPriority::MONITOR,
                plugin: $plugin,
            )];
        }

        try {
            yield from $run($channel);
        } finally {
            foreach ($listeners as [$class, $listener]) {
                HandlerListManager::global()->getListFor($class)->unregister($listener);
            }
        }
    }

    public static function sleep(Plugin $plugin, int $ticks) : Generator {
        $ok = false;
        /** @var ?ClosureTask $task */
        $task = null;
        try {
            yield from Await::promise(function($resolve) use ($plugin, $ticks, &$task) {
                $task = new ClosureTask($resolve);
                $plugin->getScheduler()->scheduleDelayedTask($task, $ticks);
            });
            $ok = true;
        } finally {
            $handler = $task?->getHandler();
            if (!$ok && $handler !== null) {
                $handler->cancel();
            }
        }
    }
}

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
