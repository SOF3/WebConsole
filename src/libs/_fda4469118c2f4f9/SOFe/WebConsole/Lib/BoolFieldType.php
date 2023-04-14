<?php

declare(strict_types=1);

namespace libs\_fda4469118c2f4f9\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;



















































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