<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\FieldMutationResponse;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\SimpleMutableFieldDesc;
use SOFe\WebConsole\Internal\Main;
use SOFe\WebConsole\Lib\EventBasedFieldDesc;
use SOFe\WebConsole\Lib\EventBasedObjectDesc;
use SOFe\WebConsole\Lib\FloatFieldType;
use SOFe\WebConsole\Lib\Vector3FieldType;

final class Players {
    const KIND = "player";

    public static function register(Main $plugin, Registry $registry) : void {
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
        ));

        $registry->registerField(new FieldDef(
            objectGroup: Group::ID,
            objectKind: self::KIND,
            path: "entity.health",
            displayName: "main-player-entity-health",
            type: new FloatFieldType,
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
            type: new Vector3FieldType,
            metadata: [],
            desc: new EventBasedFieldDesc(
                plugin: $plugin,
                events: [PlayerMoveEvent::class],
                getter: fn($player) => GeneratorUtil::empty($player->getLocation()),
                testEvent: fn($event, $player) => $event->getPlayer() === $player,
            ),
            mutableDesc: new SimpleMutableFieldDesc(function(Player $player, Vector3 $value) {
                $ok = $player->teleport($value);
                if (!$ok) {
                    return new FieldMutationResponse(
                        success: false,
                        errorCode: "Cancelled",
                        i18nMessage: "main-player-teleport-cancel",
                    );
                }

                return FieldMutationResponse::success();
            }),
        ));
    }
}
