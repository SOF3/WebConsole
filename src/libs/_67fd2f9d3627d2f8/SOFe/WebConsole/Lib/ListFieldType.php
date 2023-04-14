<?php

declare(strict_types=1);

namespace libs\_67fd2f9d3627d2f8\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;






































































































































































/**
 * @template T
 * @implements FieldType<T[]>
 */
final class ListFieldType implements FieldType {
    /**
     * @param FieldType<T> $itemType
     */
    public function __construct(
        public FieldType $itemType,
    ) {
    }

    public function serializeType() : array {
        return [
            "type" => "list",
            "item" => $this->itemType->serializeType(),
        ];
    }

    public function serializeValue($value) : mixed {
        return array_map(fn($item) => $this->itemType->serializeValue($item), $value);
    }
}