<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use libs\_7b27becfe038c4ab\SOFe\AwaitGenerator\Traverser;
use function sprintf;































































/**
 * @template V
 */
interface FieldType {
    /**
     * @return array<string, mixed>
     */
    public function serializeType() : array;

    /**
     * @param V $value
     */
    public function serializeValue($value) : mixed;
}