<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use libs\_cb07bb7a956d14fd\SOFe\AwaitGenerator\Traverser;
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