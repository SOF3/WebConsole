<?php

declare(strict_types=1);

namespace libs\_192b30f44185144c\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;












































































































































































































/**
 * @template T
 * @implements FieldType<list<T>>
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
        $output = [];
        foreach ($value as $item) {
            $output[] = $this->itemType->serializeValue($item);
        }
        return $output;
    }
}