<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Internal;

use Closure;
use Exception;
use Generator;
use RuntimeException;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Await;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Channel;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Loading;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\AddObjectEvent;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RemoveObjectEvent;

use function array_keys;
use function assert;
use function count;
use function explode;
use function in_array;
use function json_encode;
use function json_last_error_msg;
use function ltrim;
use function str_ends_with;
use function strlen;
use function substr;





























































































































































































































































































































































































final class RestartWatch extends Exception {
}