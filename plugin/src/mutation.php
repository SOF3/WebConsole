<?php

declare(strict_types=1);

namespace SOFe\WebConsole;

use Closure;
use Generator;

final class MutationDef {
    /**
     * @param array<string, string> $metadata
     * @param array<string, MutationParam> $params
     * @param Closure(array<string, mixed> $args): Generator<mixed, mixed, mixed, Traverser<MutationResponse>> $executor
     */
    public function __construct(
        public string $group,
        public string $id,
        public array $metadata,
        public array $params,
        public Closure $executor,
    ) {
    }
}

final class MutationResponse {
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public array $data,
        public bool $complete = true,
    ) {
    }
}
