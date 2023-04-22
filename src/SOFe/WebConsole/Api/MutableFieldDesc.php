<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Closure;
use Generator;
use libs\_66aebb413d7f2b17\SOFe\AwaitGenerator\Traverser;
use function sprintf;

































































/**
 * @template I
 * @template V
 */
interface MutableFieldDesc {
    /**
     * @param I $identity
     * @param V $value
     * @return Generator<mixed, mixed, mixed, FieldMutationResponse>
     */
    public function set($identity, $value) : Generator;
}