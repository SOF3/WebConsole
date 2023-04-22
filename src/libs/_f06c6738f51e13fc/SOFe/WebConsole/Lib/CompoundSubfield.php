<?php

declare(strict_types=1);

namespace libs\_f06c6738f51e13fc\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;
































































































































































































































































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

    /**
     * @param ParentT $value
     */
    public function serializeParentValue($value) : mixed {
        return $value;
    }
}