<?php

declare(strict_types=1);

namespace libs\_6c4285df04e833c0\SOFe\WebConsole\Lib;

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