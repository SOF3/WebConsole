<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use Generator;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\AwaitGenerator\PubSub;
use SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\AddObjectEvent;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\ObjectDesc;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RemoveObjectEvent;
use SOFe\WebConsole\Internal\Main;
use SOFe\WebConsole\Lib\ImmutableFieldDesc;
use SOFe\WebConsole\Lib\IntFieldType;
use SOFe\WebConsole\Lib\Metadata;
use SOFe\WebConsole\Lib\StringFieldType;
use SOFe\WebConsole\Lib\Util;
use Threaded;
use ThreadedLoggerAttachment;
use function array_shift;
use function bin2hex;
use function count;
use function microtime;
use function random_bytes;
use function strpos;
use function substr;

/**
 * @internal
 */
final class Logging {
    const KIND = "log-message";

    public static function register(Main $plugin, Registry $registry) : void {
        $queue = new LogMessageQueue(1024);
        $queue->attach($plugin);
        $registry->registerObject(new ObjectDef(
            group: Group::ID,
            kind: self::KIND,
            displayName: "main-log-message-kind",
            desc: new LogMessageObjectDesc($queue),
            metadata: [
                new Metadata\HideName,
            ],
        ));

        $registry->registerField(new FieldDef(
            objectGroup: Group::ID,
            objectKind: self::KIND,
            path: "time",
            displayName: "main-log-message-time",
            type: new IntFieldType,
            metadata: [
                new Metadata\HideFieldByDefault,
            ],
            desc: new ImmutableFieldDesc(
                getter: fn(LogMessage $message) => GeneratorUtil::empty((int) ($message->microtime * 1e6)),
            ),
        ));

        $registry->registerField(new FieldDef(
            objectGroup: Group::ID,
            objectKind: self::KIND,
            path: "verbosity",
            displayName: "main-log-message-verbosity",
            type: new StringFieldType,
            metadata: [
                new Metadata\FieldDisplayPriority(5),
            ],
            desc: new ImmutableFieldDesc(
                getter: fn(LogMessage $message) => GeneratorUtil::empty($message->level),
            ),
        ));

        $registry->registerField(new FieldDef(
            objectGroup: Group::ID,
            objectKind: self::KIND,
            path: "message.raw",
            displayName: "main-log-message-message-raw",
            type: new StringFieldType,
            metadata: [
                new Metadata\HideFieldByDefault,
            ],
            desc: new ImmutableFieldDesc(
                getter: fn(LogMessage $message) => GeneratorUtil::empty($message->message),
            ),
        ));

        $registry->registerField(new FieldDef(
            objectGroup: Group::ID,
            objectKind: self::KIND,
            path: "message.clean",
            displayName: "main-log-message-message-clean",
            type: new StringFieldType,
            metadata: [],
            desc: new ImmutableFieldDesc(
                getter: function(LogMessage $message) {
                    false && yield;
                    $text = TextFormat::clean($message->message);
                    // strip prefix. legacy issue...
                    $split = strpos($text, "]: ");
                    if ($split !== false) {
                        $text = substr($text, $split + 3);
                    }

                    return $text;
                },
            ),
        ));
    }
}

final class LogMessage {
    public function __construct(
        public string $id,
        public float $microtime,
        public mixed $level,
        public string $message,
    ) {
    }
}

final class LogMessageQueue {
    /** @var array<string, LogMessage> */
    public array $messages = [];

    /** @var PubSub<string> A pubsub topic that notifies additions or removals of a message from the queue */
    public PubSub $pubsub;

    public function __construct(private int $bufferSize) {
        $this->pubsub = new PubSub;
    }

    public function attach(Plugin $plugin) : void {
        $channel = new Threaded;
        Server::getInstance()->getLogger()->addAttachment(new LogReceiver($channel));

        Await::f2c(function() use ($channel, $plugin) {
            while (true) {
                yield from Util::sleep($plugin, 1);
                while (($item = $channel->shift()) !== null) {
                    /** @var LogMessage $item */
                    $this->observeMessage($item);
                }
            }
        });
    }

    public static function generateId() : string {
        return bin2hex(random_bytes(6));
    }

    public function observeMessage(LogMessage $message) : void {
        if (count($this->messages) >= $this->bufferSize) {
            $old = array_shift($this->messages); // remove the earliest message
            /** @var LogMessage $old */
            $this->pubsub->publish($old->id);
        }
        $this->messages[$message->id] = $message;

        $this->pubsub->publish($message->id);
    }
}

final class LogReceiver extends ThreadedLoggerAttachment {
    public function __construct(
        /** @phpstan-ignore-next-line Threaded is read from the caller */
        private Threaded $channel,
    ) {
    }

    public function log($level, $message) {
        $logMessage = new LogMessage(LogMessageQueue::generateId(), microtime(true), $level, $message);
        $this->channel[] = $logMessage;
    }
}

/**
 * @internal
 * @implements ObjectDesc<LogMessage>
 */
final class LogMessageObjectDesc implements ObjectDesc {
    public function __construct(private LogMessageQueue $queue) {
    }

    public function name($message) : string {
        return $message->id;
    }

    public function list() : Traverser {
        return Traverser::fromClosure(function() : Generator {
            foreach ($this->queue->messages as $message) {
                yield $message => Traverser::VALUE;
            }
        });
    }

    public function watch(?int $limit) : Traverser {
        return Traverser::fromClosure(function() : Generator {
            foreach ($this->queue->messages as $message) {
                yield new AddObjectEvent($message) => Traverser::VALUE;
            }

            $sub = $this->queue->pubsub->subscribe();

            try {
                while (yield from $sub->next($item)) {
                    if (isset($this->queue->messages[$item])) {
                        $message = $this->queue->messages[$item];
                        yield new AddObjectEvent($message) => Traverser::VALUE;
                    } else {
                        yield new RemoveObjectEvent($item) => Traverser::VALUE;
                    }
                }
            } finally {
                $e = yield from $sub->interrupt();
                if ($e !== null) {
                    throw $e;
                }
            }
        });
    }

    public function get(string $name) : Generator {
        false && yield;
        return $this->queue->messages[$name] ?? null;
    }
}
