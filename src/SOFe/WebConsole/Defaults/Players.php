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
use libs\_2dc28281abd90c48\SOFe\AwaitGenerator\Channel;
use libs\_2dc28281abd90c48\SOFe\AwaitGenerator\GeneratorUtil;
use libs\_2dc28281abd90c48\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\AddObjectEvent;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\ObjectDesc;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RemoveObjectEvent;
use SOFe\WebConsole\Internal\Main;
use libs\_2dc28281abd90c48\SOFe\WebConsole\Lib\EventBasedFieldDesc;
use libs\_2dc28281abd90c48\SOFe\WebConsole\Lib\FloatFieldType;
use libs\_2dc28281abd90c48\SOFe\WebConsole\Lib\Util;


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
            metadata: [],
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