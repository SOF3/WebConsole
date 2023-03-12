<?php

declare(strict_types=1);

namespace SOFe\WebConsole;

use Closure;
use Generator;
use SOFe\AwaitGenerator\Traverser;
use function array_map;
use function sprintf;

/**
 * @template I the identifier type of the object
 * @template V the type of the object field
 */
final class FieldDef {
    /**
     * @param array<string, string> $metadata
     * @param FieldType<V> $type
     */
    public function __construct(
        public string $objectGroup,
        public string $objectKind,
        public string $path,
        public FieldType $type,
        public array $metadata,
        public FieldDesc $desc,
    ) {
    }

    public function objectId() : string {
        return sprintf("%s/%s", $this->objectGroup, $this->objectKind);
    }
}

/**
 * @template I
 * @template V
 */
interface FieldDesc {
    /**
     * @param I $object
     * @return Generator<mixed, mixed, mixed, V>
     */
    public function get($object) : Generator;

    /**
     * @param I $object
     * @return Traverser<V>
     */
    public function watch($object) : Traverser;
}

/**
 * @template V
 */
interface FieldType {
    public function serializeType() : array;

    public function serializeValue($value) : mixed;
}

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
    public function serializeType() : array {
        return [
            "type" => "int64",
        ];
    }

    public function serializeValue($value) : mixed {
        return $value;
    }
}

/**
 * @implements FieldType<float>
 */
final class FloatFieldType implements FieldType {
    public function serializeType() : array {
        return [
            "type" => "float64",
        ];
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
     * @param ObjectDef<I>
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
 * @implements FieldType<T[]>
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
        return [
            "type" => "compound",
            "fields" => array_map(fn(CompoundSubfield $sub) => $sub->serializeType(), $this->subfields),
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

    public function serializeType() : array {
        return [
            "key" => $this->key,
            "name" => $this->nameI18nKey,
            "type" => $this->valueType->serializeType(),
        ];
    }

    /**
     * @param ParentT $value
     */
    public function serializeParentValue($value) : mixed {
        return $value;
    }
}