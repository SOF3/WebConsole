<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use Generator;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use libs\_02eb9eb924190945\SOFe\AwaitGenerator\Await;
use libs\_02eb9eb924190945\SOFe\AwaitGenerator\GeneratorUtil;
use libs\_02eb9eb924190945\SOFe\AwaitGenerator\PubSub;
use libs\_02eb9eb924190945\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\AddObjectEvent;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\ObjectDesc;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RemoveObjectEvent;
use SOFe\WebConsole\Internal\Main;
use libs\_02eb9eb924190945\SOFe\WebConsole\Lib\ImmutableFieldDesc;
use libs\_02eb9eb924190945\SOFe\WebConsole\Lib\IntFieldType;
use libs\_02eb9eb924190945\SOFe\WebConsole\Lib\Metadata;
use libs\_02eb9eb924190945\SOFe\WebConsole\Lib\StringFieldType;
use libs\_02eb9eb924190945\SOFe\WebConsole\Lib\Util;
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
                Metadata\DefaultDisplayMode::table(),
            ],
        ));

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