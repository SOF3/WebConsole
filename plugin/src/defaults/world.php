<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use Generator;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\world\World;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\AddObjectEvent;
use SOFe\WebConsole\FieldDef;
use SOFe\WebConsole\ImmutableFieldDesc;
use SOFe\WebConsole\IntFieldType;
use SOFe\WebConsole\Main;
use SOFe\WebConsole\ObjectDef;
use SOFe\WebConsole\ObjectDesc;
use SOFe\WebConsole\PollingFieldDesc;
use SOFe\WebConsole\Registry;
use SOFe\WebConsole\RemoveObjectEvent;
use SOFe\WebConsole\StringFieldType;
use SOFe\WebConsole\Util;


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

/**
 * @internal
 * @implements ObjectDesc<World>
 */
final class WorldObjectDesc implements ObjectDesc {
    public function __construct(private Main $plugin) {
    }

    public function name($world) : string {
        return $world->getFolderName();
    }

    public function list() : Traverser {
        return Traverser::fromClosure(function() : Generator {
            foreach ($this->plugin->getServer()->getWorldManager()->getWorlds() as $world) {
                yield $world => Traverser::VALUE;
            }
        });
    }

    public function watch(?int $limit) : Traverser {
        return Traverser::fromClosure(function() : Generator {
            yield from Util::withListener($this->plugin, [WorldLoadEvent::class, WorldUnloadEvent::class], function(Channel $channel) {
                foreach ($this->plugin->getServer()->getWorldManager()->getWorlds() as $world) {
                    yield new AddObjectEvent($world) => Traverser::VALUE;
                }

                while (true) {
                    $event = yield from $channel->receive();

                    if ($event instanceof WorldLoadEvent) {
                        yield new AddObjectEvent($event->getWorld()) => Traverser::VALUE;
                    } elseif ($event instanceof WorldUnloadEvent) {
                        yield new RemoveObjectEvent($event->getWorld()) => Traverser::VALUE;
                    }
                }
            });
        });
    }

    public function get(string $name) : Generator {
        false && yield;
        return $this->plugin->getServer()->getWorldManager()->getWorldByName($name);
    }
}
