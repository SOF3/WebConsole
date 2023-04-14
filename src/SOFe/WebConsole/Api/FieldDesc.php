<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use libs\_67fd2f9d3627d2f8\SOFe\AwaitGenerator\Traverser;
use function sprintf;













































/**
 * @template I
 * @template V
 */
interface FieldDesc {
    /**
     * @param I $identity
     * @return Generator<mixed, mixed, mixed, V>
     */
    public function get($identity) : Generator;

    /**
     * @param I $identity
     * @return Traverser<V>
     */
    public function watch($identity) : Traverser;
}