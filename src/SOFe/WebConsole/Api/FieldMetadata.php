<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Closure;
use Generator;
use libs\_66aebb413d7f2b17\SOFe\AwaitGenerator\Traverser;
use function sprintf;








































interface FieldMetadata {
    /**
     * @param array<string, mixed> $metadata
     */
    public function apply(array &$metadata) : void;
}