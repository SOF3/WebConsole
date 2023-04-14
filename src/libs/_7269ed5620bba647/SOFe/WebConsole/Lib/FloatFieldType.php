<?php

declare(strict_types=1);

namespace libs\_7269ed5620bba647\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;




































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