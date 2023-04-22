<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\world\World;
use libs\_cb07bb7a956d14fd\SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Internal\Main;
use libs\_cb07bb7a956d14fd\SOFe\WebConsole\Lib\EventBasedObjectDesc;
use libs\_cb07bb7a956d14fd\SOFe\WebConsole\Lib\ImmutableFieldDesc;
use libs\_cb07bb7a956d14fd\SOFe\WebConsole\Lib\IntFieldType;
use libs\_cb07bb7a956d14fd\SOFe\WebConsole\Lib\PollingFieldDesc;
use libs\_cb07bb7a956d14fd\SOFe\WebConsole\Lib\StringFieldType;


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
    }
}