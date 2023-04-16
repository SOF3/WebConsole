<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use pocketmine\utils\TextFormat;
use libs\_05d4a3a3240f542a\SOFe\AwaitGenerator\Await;
use libs\_05d4a3a3240f542a\SOFe\AwaitGenerator\GeneratorUtil;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Internal\Main;
use libs\_05d4a3a3240f542a\SOFe\WebConsole\Lib\ImmutableFieldDesc;
use libs\_05d4a3a3240f542a\SOFe\WebConsole\Lib\IntFieldType;
use libs\_05d4a3a3240f542a\SOFe\WebConsole\Lib\Metadata;
use libs\_05d4a3a3240f542a\SOFe\WebConsole\Lib\StreamingObjectDesc;
use libs\_05d4a3a3240f542a\SOFe\WebConsole\Lib\StringFieldType;
use libs\_05d4a3a3240f542a\SOFe\WebConsole\Lib\Util;
use Threaded;
use ThreadedLoggerAttachment;
use function microtime;
use function strpos;
use function substr;










































































































final class LogMessage {
    public function __construct(
        public float $microtime,
        public mixed $level,
        public string $message,
    ) {
    }
}