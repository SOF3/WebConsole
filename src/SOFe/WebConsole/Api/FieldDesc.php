<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use libs\_ea943571e36f3c14\SOFe\AwaitGenerator\Traverser;
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