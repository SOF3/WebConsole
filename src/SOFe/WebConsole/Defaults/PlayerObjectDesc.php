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
use libs\_fda4469118c2f4f9\SOFe\AwaitGenerator\Channel;
use libs\_fda4469118c2f4f9\SOFe\AwaitGenerator\GeneratorUtil;
use libs\_fda4469118c2f4f9\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\AddObjectEvent;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\ObjectDesc;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RemoveObjectEvent;
use SOFe\WebConsole\Internal\Main;
use libs\_fda4469118c2f4f9\SOFe\WebConsole\Lib\EventBasedFieldDesc;
use libs\_fda4469118c2f4f9\SOFe\WebConsole\Lib\FloatFieldType;
use libs\_fda4469118c2f4f9\SOFe\WebConsole\Lib\Util;























































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