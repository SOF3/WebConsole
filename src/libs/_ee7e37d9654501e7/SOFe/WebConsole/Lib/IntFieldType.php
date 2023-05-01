<?php

declare(strict_types=1);

namespace libs\_ee7e37d9654501e7\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;























/**
 * @implements FieldType<int>
 */
final class IntFieldType implements FieldType {
    /**
     * Min and max bounds are both inclusive.
     */
    public function __construct(
        public bool $isTimestamp = false,
        public ?int $min = null,
        public ?int $max = null,
    ) {
    }

    public function serializeType() : array {
        $ret = ["type" => "int64"];
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