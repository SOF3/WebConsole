<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use libs\_ee7e37d9654501e7\SOFe\AwaitGenerator\Traverser;
use function sprintf;






































interface FieldMetadata {
    /**
     * @param array<string, mixed> $metadata
     */
    public function apply(array &$metadata) : void;
}