<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Closure;
use Generator;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Traverser;
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