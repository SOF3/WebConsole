<?php

declare(strict_types=1);

namespace libs\_6c4285df04e833c0\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;















































































































































































/**
 * @template T
 * @implements FieldType<?T>
 */
final class NullableFieldType implements FieldType {
    /**
     * @param FieldType<T> $itemType
     */
    public function __construct(
        public FieldType $itemType,
    ) {
    }

    public function serializeType() : array {
        return [
            "type" => "nullable",
            "item" => $this->itemType->serializeType(),
        ];
    }

    public function serializeValue($value) : mixed {
        if ($value === null) {
            return null;
        }

        return $this->itemType->serializeValue($value);
    }
}