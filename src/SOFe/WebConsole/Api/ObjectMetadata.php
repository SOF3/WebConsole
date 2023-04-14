<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use libs\_67fd2f9d3627d2f8\SOFe\AwaitGenerator\Traverser;
use function sprintf;















































interface ObjectMetadata {
    /**
     * @param array<string, mixed> $metadata
     */
    public function apply(array &$metadata) : void;
}