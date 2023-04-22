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
 * @implements MutableFieldDesc<I, V>
 */
final class SimpleMutableFieldDesc implements MutableFieldDesc {
    /**
     * @param Closure(I, V): FieldMutationResponse $closure
     */
    public function __construct(
        public Closure $closure,
    ) {
    }

    public function set($identity, $value) : Generator {
        false && yield;
        return ($this->closure)($identity, $value);
    }
}