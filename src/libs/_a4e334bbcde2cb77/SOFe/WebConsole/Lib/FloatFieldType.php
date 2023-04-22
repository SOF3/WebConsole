<?php

declare(strict_types=1);

namespace libs\_a4e334bbcde2cb77\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;



















































/**
 * @implements FieldType<float>
 */
final class FloatFieldType implements FieldType {
    public function __construct(
        public bool $isTimestamp = false,
        public ?float $min = null,
        public ?float $max = null,
    ) {
    }

    public function serializeType() : array {
        $ret = ["type" => "float64"];
        if ($this->isTimestamp) {
            $ret["is_timestamp"] = true;
        }
        if ($this->min !== null) {
            $ret["min"] = $this->min;
        }
        if ($this->max !== null) {
            $ret["max"] = $this->max;
        }
        return $ret;
    }

    public function serializeValue($value) : mixed {
        return $value;
    }
}