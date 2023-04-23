<?php

declare(strict_types=1);

namespace libs\_6c4285df04e833c0\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;








































































































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