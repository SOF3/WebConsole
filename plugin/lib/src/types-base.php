<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;

// This file contains base field types that reflect webapp capabilities.

// Field types are defined in the `lib` package instead of `api`
// because implementations of `FieldType` may change or add,
// subject to new field types supported by the web frontend or other clients.
// Plugins may also implement their own field types that serialize to an existing type.

/**
 * @implements FieldType<string>
 */
final class StringFieldType implements FieldType {
    public function serializeType() : array {
        return [
            "type" => "string",
        ];
    }

    public function serializeValue($value) : mixed {
        return $value;
    }
}

/**
 * @implements FieldType<int>
 */
final class IntFieldType implements FieldType {
    /**
     * Min and max bounds are both inclusive.
     */
    public function __construct(
        public bool $isTimestamp = false,
        public ?int $min = null,
        public ?int $max = null,
    ) {
    }

    public function serializeType() : array {
        $ret = ["type" => "int64"];
        if ($this->isTimestamp) {
            $ret["is_timestamp"] = true;
        }
        if ($this->min !== null) {
            $ret["min"] = $this->min;
        }
        if ($this->max !== null) {
            $ret["max"] = $this->max;
        }
        return $ret;
    }

    public function serializeValue($value) : mixed {
        return $value;
    }
}

/**
 * @implements FieldType<float>
 */
final class FloatFieldType implements FieldType {
    /**
     * Min and max bounds are both inclusive.
     */
    public function __construct(
        public bool $isTimestamp = false,
        public ?float $min = null,
        public ?float $max = null,
    ) {
    }

    public function serializeType() : array {
        $ret = ["type" => "float64"];
        if ($this->isTimestamp) {
            $ret["is_timestamp"] = true;
        }
        if ($this->min !== null) {
            $ret["min"] = $this->min;
        }
        if ($this->max !== null) {
            $ret["max"] = $this->max;
        }
        return $ret;
    }

    public function serializeValue($value) : mixed {
        return $value;
    }
}

/**
 * @implements FieldType<bool>
 */
final class BoolFieldType implements FieldType {
    public function serializeType() : array {
        return [
            "type" => "bool",
        ];
    }

    public function serializeValue($value) : mixed {
        return $value;
    }
}

/**
 * @template E
 * @implements FieldType<E>
 */
final class EnumFieldType implements FieldType {
    /**
     * @param EnumOption[] $options
     * @param Closure(E): int $enumIndexer
     */
    public function __construct(
        public array $options,
        public Closure $enumIndexer,
    ) {
    }

    public function serializeType() : array {
        return [
            "type" => "enum",
            "options" => array_map(fn(EnumOption $option) => $option->serializeType(), $this->options),
        ];
    }

    public function serializeValue($value) : mixed {
        return $value;
    }
}

final class EnumOption {
    public function __construct(
        public string $id,
        public string $i18nKey,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeType() : array {
        return [
            "id" => $this->id,
            "i18n" => $this->i18nKey,
        ];
    }
}

/**
 * @template I
 * @implements FieldType<I>
 */
final class ObjectRefFieldType implements FieldType {
    /**
     * @param ObjectDef<I> $theirKind
     */
    public function __construct(
        public ObjectDef $theirKind,
    ) {
    }

    public function serializeType() : array {
        return [
            "type" => "object",
            "group" => $this->theirKind->group,
            "kind" => $this->theirKind->kind,
        ];
    }

    public function serializeValue($value) : mixed {
        return $this->theirKind->desc->name($value);
    }
}

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

/**
 * @template T
 * @implements FieldType<T>
 */
final class CompoundFieldType implements FieldType {
    /**
     * @param CompoundSubfield<T, mixed>[] $subfields
     */
    public function __construct(
        public array $subfields,
    ) {
    }

    public function serializeType() : array {
        $subfields = [];
        foreach ($this->subfields as $subfield) {
            $subfields[] = $subfield->serializeType();
        }

        return [
            "type" => "compound",
            "fields" => $subfields,
        ];
    }

    public function serializeValue($value) : mixed {
        $output = [];
        foreach ($this->subfields as $field) {
            $output[$field->key] = $field->valueType->serializeValue($value);
            ;
        }
        return $output;
    }
}

/**
 * @template ParentT
 * @template ValueT
 */
final class CompoundSubfield {
    /**
     * @param FieldType<ValueT> $valueType
     */
    public function __construct(
        public string $key,
        public string $nameI18nKey,
        public FieldType $valueType,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeType() : array {
        return [
            "key" => $this->key,
            "name" => $this->nameI18nKey,
            "type" => $this->valueType->serializeType(),
        ];
    }
}
