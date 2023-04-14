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