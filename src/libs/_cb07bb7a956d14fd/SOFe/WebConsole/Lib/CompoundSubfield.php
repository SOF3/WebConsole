<?php

declare(strict_types=1);

namespace libs\_cb07bb7a956d14fd\SOFe\WebConsole\Lib;

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
}