<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Lib;

use Closure;
use Generator;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\ObjectDesc;

/**
 * @template I
 * @template AddE of Event
 * @template RemoveE of Event
 * @implements ObjectDesc<I>
 */
final class EventBasedObjectDesc implements ObjectDesc {
    /** @var ?IndexedPubSub<RemoveE> */
    private ?IndexedPubSub $removePubSub = null;

    /**
     * @param Closure(I): string $name
     * @param Closure(string): ?I $get
     * @param Closure(): I[] $list
     * @param Closure(I): bool $isValid
     * @param class-string<AddE> $addEvent
     * @param class-string<RemoveE> $removeEvent
     */
    public function __construct(
        private Plugin $plugin,
        public Closure $name,
        public Closure $get,
        public Closure $list,
        public Closure $isValid,
        public string $addEvent,
        public Closure $resolveAddEvent,
        public string $removeEvent,
        public Closure $resolveRemoveEvent,
    ) {
    }

    public function name($object) : string {
        return ($this->name)($object);
    }

    public function get(string $name) : Generator {
        false && yield;
        return ($this->get)($name);
    }

    public function watchAdd(bool $listOnly, ?int $limit) : Traverser {
        return Traverser::fromClosure(function() use ($listOnly) {
            foreach (($this->list)() as $identity) {
                if (($this->isValid)($identity)) {
                    yield $identity => Traverser::VALUE;
                }
            }

            if ($listOnly) {
                return;
            }

            yield from Util::withListener($this->plugin, [$this->addEvent], function(Channel $channel) {
                while (true) {
                    $event = yield from $channel->receive();

                    $identity = ($this->resolveAddEvent)($event);
                    if ($identity !== null) {
                        yield $identity => Traverser::VALUE;
                    }
                }
            });
        });
    }

    public function watchRemove($object) : Generator {
        $name = ($this->name)($object);
        $ps = $this->ensureRemoveListener();
        yield from $ps->watchOnce($name);
    }

    /**
     * @return IndexedPubSub<RemoveE>
     */
    private function ensureRemoveListener() : IndexedPubSub {
        if ($this->removePubSub !== null) {
            return $this->removePubSub;
        }

        /** @var IndexedPubSub<RemoveE> $ps */
        $ps = new IndexedPubSub;
        $this->removePubSub = $ps;

        Server::getInstance()->getPluginManager()->registerEvent($this->removeEvent, function(Event $event) use ($ps) {
            /** @var RemoveE $event */
            $identity = ($this->resolveRemoveEvent)($event);
            if ($identity !== null) {
                $name = ($this->name)($identity);
                $ps->publish($name, $event);
            }
        }, EventPriority::MONITOR, $this->plugin);

        return $this->removePubSub;
    }
}
