<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use Generator;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Await;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\GeneratorUtil;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\PubSub;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\AddObjectEvent;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\ObjectDesc;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RemoveObjectEvent;
use SOFe\WebConsole\Internal\Main;
use libs\_f00b9901125756a3\SOFe\WebConsole\Lib\ImmutableFieldDesc;
use libs\_f00b9901125756a3\SOFe\WebConsole\Lib\IntFieldType;
use libs\_f00b9901125756a3\SOFe\WebConsole\Lib\Metadata;
use libs\_f00b9901125756a3\SOFe\WebConsole\Lib\StringFieldType;
use libs\_f00b9901125756a3\SOFe\WebConsole\Lib\Util;
use Threaded;
use ThreadedLoggerAttachment;
use function array_shift;
use function bin2hex;
use function count;
use function microtime;
use function random_bytes;
use function strpos;
use function substr;
































































































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