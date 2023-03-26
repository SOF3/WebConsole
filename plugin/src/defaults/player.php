<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use Generator;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\AddObjectEvent;
use SOFe\WebConsole\EventBasedFieldDesc;
use SOFe\WebConsole\FieldDef;
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
            displayName: "main-player-entity-health",
            type: new FloatFieldType,
            metadata: [],
            desc: new EventBasedFieldDesc(
                plugin: $plugin,
                events: [EntityDamageEvent::class, EntityRegainHealthEvent::class],
                getter: fn($player) => GeneratorUtil::empty($player->getHealth()),
                testEvent: fn($event, $player) => $event->getEntity() === $player,
            ),
        ));

        foreach ([
            ["x", fn(Player $player) => (float) $player->getLocation()->getX()],
            ["y", fn(Player $player) => (float) $player->getLocation()->getY()],
            ["z", fn(Player $player) => (float) $player->getLocation()->getZ()],
        ] as [$name, $getter]) {
            $registry->registerField(new FieldDef(
                objectGroup: Group::ID,
                objectKind: self::KIND,
                path: "entity.location.$name",
                displayName: "main-player-entity-location-$name",
                type: new FloatFieldType,
                metadata: [],
                desc: new EventBasedFieldDesc(
                    plugin: $plugin,
                    events: [PlayerMoveEvent::class],
                    getter: fn($player) => GeneratorUtil::empty($getter($player)),
                    testEvent: fn($event, $player) => $event->getPlayer() === $player,
                ),
            ));
        }
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

    public function watch(?int $limit) : Traverser {
        return Traverser::fromClosure(function() : Generator {
            yield from Util::withListener($this->plugin, [PlayerLoginEvent::class, PlayerQuitEvent::class], function(Channel $channel) {
                foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
                    yield new AddObjectEvent($player) => Traverser::VALUE;
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
