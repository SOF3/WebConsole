<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use Generator;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use libs\_1ef0ff44a3bf42da\SOFe\AwaitGenerator\Await;
use libs\_1ef0ff44a3bf42da\SOFe\AwaitGenerator\GeneratorUtil;
use libs\_1ef0ff44a3bf42da\SOFe\AwaitGenerator\PubSub;
use libs\_1ef0ff44a3bf42da\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\AddObjectEvent;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\ObjectDesc;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RemoveObjectEvent;
use SOFe\WebConsole\Internal\Main;
use libs\_1ef0ff44a3bf42da\SOFe\WebConsole\Lib\ImmutableFieldDesc;
use libs\_1ef0ff44a3bf42da\SOFe\WebConsole\Lib\IntFieldType;
use libs\_1ef0ff44a3bf42da\SOFe\WebConsole\Lib\Metadata;
use libs\_1ef0ff44a3bf42da\SOFe\WebConsole\Lib\StringFieldType;
use libs\_1ef0ff44a3bf42da\SOFe\WebConsole\Lib\Util;
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