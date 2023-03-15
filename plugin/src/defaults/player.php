<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use Generator;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\AddObjectEvent;
use SOFe\WebConsole\FieldDef;
use SOFe\WebConsole\FieldDesc;
use SOFe\WebConsole\FloatFieldType;
use SOFe\WebConsole\Main;
use SOFe\WebConsole\ObjectDef;
use SOFe\WebConsole\ObjectDesc;
use SOFe\WebConsole\Registry;
use SOFe\WebConsole\RemoveObjectEvent;
use SOFe\WebConsole\Util;


/**
 * @internal
 */
final class Players {
    const KIND = "player";

    public static function register(Main $plugin, Registry $registry) : void {
        $registry->registerObject(new ObjectDef(
            group: Group::ID,
            kind: self::KIND,
            displayName: "main-player-kind",
            desc: new PlayerObjectDesc($plugin),
        ));
        $registry->registerField(new FieldDef(
            objectGroup: Group::ID,
            objectKind: self::KIND,
            path: "entity.health",
            type: new FloatFieldType,
            metadata: [],
            desc: new HealthFieldDesc($plugin),
        ));
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
            yield from Util::withListener($this->plugin, [PlayerLoginEvent::class, PlayerQuitEvent::class], function(Channel $channel) use ($withExisting) {
                if ($withExisting) {
                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
                        yield new AddObjectEvent($player) => Traverser::VALUE;
                    }
                }

                while (true) {
                    $event = yield from $channel->receive();

                    if ($event instanceof PlayerLoginEvent) {
                        yield new AddObjectEvent($event->getPlayer()) => Traverser::VALUE;
                    } elseif ($event instanceof PlayerQuitEvent) {
                        yield new RemoveObjectEvent($event->getPlayer()) => Traverser::VALUE;
                    }
                }
            });
        });
    }

    public function get(string $name) : Generator {
        false && yield;
        return $this->plugin->getServer()->getPlayerExact($name);
    }
}

/**
 * @implements FieldDesc<Player, float>
 */
final class HealthFieldDesc implements FieldDesc {
    public function __construct(private Main $plugin) {
    }

    public function get($player) : Generator {
        false && yield;
        return $player->getHealth();
    }

    public function watch($player) : Traverser {
        return Traverser::fromClosure(function() use ($player) {
            $previous = $player->getHealth();
            yield $previous => Traverser::VALUE;

            yield from Util::withListener($this->plugin, [EntityDamageEvent::class, EntityRegainHealthEvent::class], function(Channel $channel) use ($player, &$previous) {
                while (true) {
                    $event = yield from $channel->receive();
                    if ($event->getEntity() === $player) {
                        $health = $player->getHealth();
                        if ($health !== $previous) {
                            $previous = $health;
                            yield $health => Traverser::VALUE;
                        }
                    }
                }
            });
        });
    }
}
