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























































































final class LogMessage {
    public function __construct(
        public string $id,
        public float $microtime,
        public mixed $level,
        public string $message,
    ) {
    }
}