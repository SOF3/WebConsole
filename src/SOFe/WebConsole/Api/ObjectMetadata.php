<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Exception;
use Generator;
use libs\_a56d4359543efb82\SOFe\AwaitGenerator\Traverser;
use function sprintf;















































interface ObjectMetadata {
    /**
     * @param array<string, mixed> $metadata
     */
    public function apply(array &$metadata) : void;
}