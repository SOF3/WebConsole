<?php

declare(strict_types=1);

namespace libs\_67fd2f9d3627d2f8\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;





















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