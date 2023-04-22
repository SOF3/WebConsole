<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use pocketmine\utils\TextFormat;
use libs\_66aebb413d7f2b17\SOFe\AwaitGenerator\Await;
use libs\_66aebb413d7f2b17\SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Internal\Main;
use libs\_66aebb413d7f2b17\SOFe\WebConsole\Lib\ImmutableFieldDesc;
use libs\_66aebb413d7f2b17\SOFe\WebConsole\Lib\IntFieldType;
use libs\_66aebb413d7f2b17\SOFe\WebConsole\Lib\Metadata;
use libs\_66aebb413d7f2b17\SOFe\WebConsole\Lib\StreamingObjectDesc;
use libs\_66aebb413d7f2b17\SOFe\WebConsole\Lib\StringFieldType;
use libs\_66aebb413d7f2b17\SOFe\WebConsole\Lib\Util;
use Threaded;
use ThreadedLoggerAttachment;
use function microtime;
use function strpos;
use function substr;

final class Logging {
    const KIND = "log-message";

    public static function register(Main $plugin, Registry $registry) : void {
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