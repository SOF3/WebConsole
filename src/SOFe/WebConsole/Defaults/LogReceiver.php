<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use pocketmine\utils\TextFormat;
use libs\_ee7e37d9654501e7\SOFe\AwaitGenerator\Await;
use libs\_ee7e37d9654501e7\SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Internal\Main;
use libs\_ee7e37d9654501e7\SOFe\WebConsole\Lib\ImmutableFieldDesc;
use libs\_ee7e37d9654501e7\SOFe\WebConsole\Lib\IntFieldType;
use libs\_ee7e37d9654501e7\SOFe\WebConsole\Lib\Metadata;
use libs\_ee7e37d9654501e7\SOFe\WebConsole\Lib\StreamingObjectDesc;
use libs\_ee7e37d9654501e7\SOFe\WebConsole\Lib\StringFieldType;
use libs\_ee7e37d9654501e7\SOFe\WebConsole\Lib\Util;
use Threaded;
use ThreadedLoggerAttachment;
use function microtime;
use function strpos;
use function substr;





















































































































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