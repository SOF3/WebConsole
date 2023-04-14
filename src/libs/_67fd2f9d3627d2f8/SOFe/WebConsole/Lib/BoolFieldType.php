<?php

declare(strict_types=1);

namespace libs\_67fd2f9d3627d2f8\SOFe\WebConsole\Lib;

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