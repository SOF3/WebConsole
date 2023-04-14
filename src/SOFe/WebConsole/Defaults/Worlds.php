<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use Generator;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\world\World;
use libs\_67fd2f9d3627d2f8\SOFe\AwaitGenerator\Channel;
use libs\_67fd2f9d3627d2f8\SOFe\AwaitGenerator\GeneratorUtil;
use libs\_67fd2f9d3627d2f8\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\AddObjectEvent;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\ObjectDesc;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RemoveObjectEvent;
use SOFe\WebConsole\Internal\Main;
use libs\_67fd2f9d3627d2f8\SOFe\WebConsole\Lib\ImmutableFieldDesc;
use libs\_67fd2f9d3627d2f8\SOFe\WebConsole\Lib\IntFieldType;
use libs\_67fd2f9d3627d2f8\SOFe\WebConsole\Lib\PollingFieldDesc;
use libs\_67fd2f9d3627d2f8\SOFe\WebConsole\Lib\StringFieldType;
use libs\_67fd2f9d3627d2f8\SOFe\WebConsole\Lib\Util;


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
            desc: new WorldObjectDesc($plugin),
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