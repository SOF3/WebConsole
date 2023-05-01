<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Internal\Main;
use SOFe\WebConsole\Lib\ImmutableFieldDesc;
use SOFe\WebConsole\Lib\IntFieldType;
use SOFe\WebConsole\Lib\Metadata;
use SOFe\WebConsole\Lib\StreamingObjectDesc;
use SOFe\WebConsole\Lib\StringFieldType;
use SOFe\WebConsole\Lib\Util;
use Threaded;
use ThreadedLoggerAttachment;
use function microtime;
use function strpos;
use function substr;

/**
 * @internal
 */
final class Logging {
    const KIND = "log-message";

    public static function registerKind(Main $plugin, Registry $registry) : void {
        $desc = new StreamingObjectDesc(1024);
        self::attachLogger($plugin, $desc);
        $registry->registerObject(new ObjectDef(
            group: Group::ID,
            kind: self::KIND,
            displayName: "main-log-message-kind",
            desc: $desc,
            metadata: [
                new Metadata\HideName,
                new Metadata\DescendingSort,
                Metadata\DefaultDisplayMode::table(),
            ],
        ));
    }

    public static function registerFields(Registry $registry) : void {
        $registry->registerField(new FieldDef(
            objectGroup: Group::ID,
            objectKind: self::KIND,
            path: "time",
            displayName: "main-log-message-time",
            type: new IntFieldType(isTimestamp: true),
            metadata: [
                new Metadata\FieldDisplayPriority(10),
            ],
            desc: new ImmutableFieldDesc(
                getter: fn($object) => GeneratorUtil::empty((int) ($object->object->microtime * 1e6)),
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
                getter: fn($object) => GeneratorUtil::empty($object->object->level),
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
                getter: fn($object) => GeneratorUtil::empty($object->object->message),
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
                getter: function($object) {
                    false && yield;
                    $text = TextFormat::clean($object->object->message);
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

    /**
     * @param StreamingObjectDesc<LogMessage> $desc
     */
    private static function attachLogger(Main $plugin, StreamingObjectDesc $desc) : void {
        $channel = new Threaded;
        $plugin->getServer()->getLogger()->addAttachment(new LogReceiver($channel));

        Await::f2c(function() use ($channel, $plugin, $desc) {
            while (true) {
                yield from Util::sleep($plugin, 1);
                while (($item = $channel->shift()) !== null) {
                    /** @var LogMessage $item */
                    $desc->push($item);
                }
            }
        });
    }
}

final class LogMessage {
    public function __construct(
        public float $microtime,
        public mixed $level,
        public string $message,
    ) {
    }
}

final class LogReceiver extends ThreadedLoggerAttachment {
    public function __construct(
        /** @phpstan-ignore-next-line Threaded is read from the caller */
        private Threaded $channel,
    ) {
    }

    public function log($level, $message) {
        $logMessage = new LogMessage(microtime(true), $level, $message);
        $this->channel[] = $logMessage;
    }
}
