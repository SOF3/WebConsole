<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Internal\Main;
use SOFe\WebConsole\Lib\EventBasedFieldDesc;
use SOFe\WebConsole\Lib\EventBasedObjectDesc;
use SOFe\WebConsole\Lib\FloatFieldType;
use SOFe\WebConsole\Lib\MainGroup;
use SOFe\WebConsole\Lib\PositionFieldType;

/**
 * @internal
 */
final class Players {
    public const KIND = MainGroup::PLAYER_KIND;

    public static function registerKind(Main $plugin, Registry $registry) : void {
        $registry->registerObject(new ObjectDef(
            group: Group::ID,
            kind: self::KIND,
            displayName: "main-player-kind",
            desc: new EventBasedObjectDesc(
                plugin: $plugin,
                name: fn(Player $player) => $player->getName(),
                get: fn(string $name) => $plugin->getServer()->getPlayerExact($name),
                list: fn() => $plugin->getServer()->getOnlinePlayers(),
                isValid: fn(Player $player) => $player->isOnline(),
                addEvent: PlayerLoginEvent::class,
                resolveAddEvent: fn(PlayerLoginEvent $event) => $event->getPlayer(),
                removeEvent: PlayerQuitEvent::class,
                resolveRemoveEvent: fn(PlayerQuitEvent $event) => $event->getPlayer(),
            ),
            metadata: [],
        ));
    }

    public static function registerFields(Main $plugin, Registry $registry) : void {
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

        $registry->registerField(new FieldDef(
            objectGroup: Group::ID,
            objectKind: self::KIND,
            path: "entity.position",
            displayName: "main-player-entity-position",
            type: new PositionFieldType($registry),
            metadata: [],
            desc: new EventBasedFieldDesc(
                plugin: $plugin,
                events: [PlayerMoveEvent::class, EntityTeleportEvent::class],
                getter: fn($player) => GeneratorUtil::empty($player->getLocation()),
                testEvent: fn($event, $player) => match (true) {
                    $event instanceof PlayerMoveEvent => $event->getPlayer() === $player,
                    $event instanceof EntityTeleportEvent => $event->getEntity() === $player,
                },
            ),
        ));
    }
}
