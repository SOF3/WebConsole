<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\player\Player;
use pocketmine\world\World;
use RuntimeException;
use SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Internal\Main;
use SOFe\WebConsole\Lib\EventBasedFieldDesc;
use SOFe\WebConsole\Lib\EventBasedObjectDesc;
use SOFe\WebConsole\Lib\ImmutableFieldDesc;
use SOFe\WebConsole\Lib\IntFieldType;
use SOFe\WebConsole\Lib\ListFieldType;
use SOFe\WebConsole\Lib\ObjectRefFieldType;
use SOFe\WebConsole\Lib\PollingFieldDesc;
use SOFe\WebConsole\Lib\StringFieldType;


/**
 * @internal
 */
final class Worlds {
    const KIND = "world";

    public static function register(Main $plugin, Registry $registry) : void {
        $registry->registerObject(new ObjectDef(
            group: Group::ID,
            kind: self::KIND,
            displayName: "main-world-kind",
            desc: new EventBasedObjectDesc(
                plugin: $plugin,
                name: fn(World $world) => $world->getFolderName(),
                get: fn(string $name) => $plugin->getServer()->getWorldManager()->getWorldByName($name),
                list: fn() => $plugin->getServer()->getWorldManager()->getWorlds(),
                isValid: fn(World $world) => $world->isLoaded(),
                addEvent: WorldLoadEvent::class,
                resolveAddEvent: fn(WorldLoadEvent $event) => $event->getWorld(),
                removeEvent: WorldUnloadEvent::class,
                resolveRemoveEvent: fn(WorldUnloadEvent $event) => $event->getWorld(),
            ),
            metadata: [],
        ));

        $registry->registerField(new FieldDef(
            objectGroup: Group::ID,
            objectKind: self::KIND,
            path: "displayName",
            displayName: "main-world-display-name",
            type: new StringFieldType,
            metadata: [],
            desc: new ImmutableFieldDesc(fn(World $world) => GeneratorUtil::empty($world->getDisplayName())),
        ));

        $registry->registerField(new FieldDef(
            objectGroup: Group::ID,
            objectKind: self::KIND,
            path: "time",
            displayName: "main-world-time",
            type: new IntFieldType,
            metadata: [],
            desc: new PollingFieldDesc(
                plugin: $plugin,
                getter: fn(World $world) => GeneratorUtil::empty($world->getTimeOfDay()),
                periodTicks: 20,
            ),
        ));

        $playerObjectDef = $registry->getObjectDef(Group::ID, Players::KIND) ?? throw new RuntimeException("incorrect startup order");
        /** @var ListFieldType<Player> $playersFieldType */
        $playersFieldType = new ListFieldType(new ObjectRefFieldType($playerObjectDef));
        $registry->registerField(new FieldDef(
            objectGroup: Group::ID,
            objectKind: self::KIND,
            path: "players",
            displayName: "main-world-players",
            type: $playersFieldType,
            metadata: [],
            desc: new EventBasedFieldDesc(
                plugin: $plugin,
                events: [
                    PlayerJoinEvent::class,
                    PlayerQuitEvent::class,
                    EntityTeleportEvent::class,
                ],
                getter: fn(World $world) => GeneratorUtil::empty($world->getPlayers()),
                testEvent: function(PlayerJoinEvent|PlayerQuitEvent|EntityTeleportEvent $event, World $world) {
                    return match (true) {
                        $event instanceof PlayerJoinEvent => $event->getPlayer()->getWorld() === $world,
                        $event instanceof PlayerQuitEvent => $event->getPlayer()->getWorld() === $world,
                        $event instanceof EntityTeleportEvent => $event->getEntity()->getWorld() === $world,
                    };
                },
            ),
        ));
    }
}
