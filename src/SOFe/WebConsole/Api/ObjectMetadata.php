<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Exception;
use Generator;
use libs\_85f6d346dd7f97fb\SOFe\AwaitGenerator\Traverser;
use function sprintf;















































interface ObjectMetadata {
    /**
     * @param array<string, mixed> $metadata
     */
    public function apply(array &$metadata) : void;
}