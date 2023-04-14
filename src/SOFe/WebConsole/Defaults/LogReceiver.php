<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use Generator;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use libs\_734bfd15e9991e6b\SOFe\AwaitGenerator\Await;
use libs\_734bfd15e9991e6b\SOFe\AwaitGenerator\GeneratorUtil;
use libs\_734bfd15e9991e6b\SOFe\AwaitGenerator\PubSub;
use libs\_734bfd15e9991e6b\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\AddObjectEvent;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\ObjectDesc;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RemoveObjectEvent;
use SOFe\WebConsole\Internal\Main;
use libs\_734bfd15e9991e6b\SOFe\WebConsole\Lib\ImmutableFieldDesc;
use libs\_734bfd15e9991e6b\SOFe\WebConsole\Lib\IntFieldType;
use libs\_734bfd15e9991e6b\SOFe\WebConsole\Lib\Metadata;
use libs\_734bfd15e9991e6b\SOFe\WebConsole\Lib\StringFieldType;
use libs\_734bfd15e9991e6b\SOFe\WebConsole\Lib\Util;
use Threaded;
use ThreadedLoggerAttachment;
use function array_shift;
use function bin2hex;
use function count;
use function microtime;
use function random_bytes;
use function strpos;
use function substr;










































































































































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