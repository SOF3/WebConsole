<?php

declare(strict_types=1);

namespace SOFe\WebConsole;

use Closure;
use Generator;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use SOFe\AwaitGenerator\Channel;

final class Util {
    /**
     * @template E of Event
     * @param list<class-string<E>> $classes
     * @param Closure(Channel<E>): Generator<mixed, mixed, mixed, R> $run
     * @return Generator<mixed, mixed, mixed, R>
     */
    public static function withListener(Main $plugin, array $classes, Closure $run) : Generator {
        $channel = new Channel;

        $listeners = [];
        foreach ($classes as $class) {
            $listeners[] = [$class, $plugin->getServer()->getPluginManager()->registerEvent(
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
}
