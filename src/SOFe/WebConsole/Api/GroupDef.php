<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Exception;
use Generator;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Traverser;
use function sprintf;

final class GroupDef {
    public function __construct(
        public string $id,
        public string $displayName,
        public int $displayPriority,
    ) {
    }
}