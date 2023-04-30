<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Exception;
use Generator;
use libs\_37f8d49eb6299cb1\SOFe\AwaitGenerator\Traverser;
use function sprintf;

final class GroupDef {
    public function __construct(
        public string $id,
        public string $displayName,
        public int $displayPriority,
    ) {
    }
}