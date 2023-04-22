<?php

declare(strict_types=1);

namespace libs\_f06c6738f51e13fc\SOFe\WebConsole\Lib;

use AssertionError;
use Closure;
use Generator;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Await;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Channel;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\PubSub;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Traverser;

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