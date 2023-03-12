<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use Generator;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\AddObjectEvent;
use SOFe\WebConsole\Main;
use SOFe\WebConsole\ObjectDef;
use SOFe\WebConsole\ObjectDesc;
use SOFe\WebConsole\Registry;
use SOFe\WebConsole\RemoveObjectEvent;
use function array_map;

/**
 * @internal
 */
final class Players {
    public static function register(Main $plugin, Registry $registry) : void {
        $registry->registerObject(new ObjectDef(
            group: Group::ID,
            kind: "player",
            displayName: "main-player-kind",
            desc: new PlayerObjectDesc($plugin),
        ));
        $registry->provideFluent("main/player", "en", <<<EOF
            main-player-kind = Online Players
            EOF);
    }
}

/**
 * @internal
 * @implements ObjectDesc<Player>
 */
final class PlayerObjectDesc implements ObjectDesc {
    public function __construct(private Main $plugin) {
    }

    public function name($player) : string {
        return $player->getName();
    }

    public function list() : Traverser {
        return Traverser::fromClosure(function() : Generator {
            foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
                yield $player => Traverser::VALUE;
            }
        });
    }

    public function watch(bool $withExisting) : Traverser {
        return Traverser::fromClosure(function() use ($withExisting) : Generator {
            $channel = new Channel;

            $listeners = array_map(fn($class) => $this->plugin->getServer()->getPluginManager()->registerEvent(
                event: $class,
                handler: fn($event) => $channel->sendWithoutWait($event),
                priority: EventPriority::MONITOR,
                plugin: $this->pulgin,
            ), [PlayerLoginEvent::class, PlayerQuitEvent::class]);

            try {
                if ($withExisting) {
                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
                        yield new AddObjectEvent($player) => Traverser::VALUE;
                    }
                }

                while (true) {
                    $event = yield from $channel->receive();

                    if ($event instanceof PlayerLoginEvent) {
                        yield new AddObjectEvent($player) => Traverser::VALUE;
                    } elseif ($event instanceof PlayerQuitEvent) {
                        yield new RemoveObjectEvent($player) => Traverser::VALUE;
                    }
                }
            } finally {
                foreach ($listeners as $listener) {
                    HandlerListManager::global()->getListFor(PlayerLoginEvent::class)->unregister($listener);
                }
            }
        });
    }

    public function get(string $name) : Generator {
        false && yield;
        return $this->plugin->getServer()->getPlayerExact($name);
    }
}
