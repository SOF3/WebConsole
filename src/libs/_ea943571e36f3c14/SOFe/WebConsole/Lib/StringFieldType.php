<?php

declare(strict_types=1);

namespace libs\_ea943571e36f3c14\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;








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