<?php

declare(strict_types=1);

namespace libs\_192b30f44185144c\SOFe\WebConsole\Lib;

use pocketmine\math\Vector3;
use SOFe\WebConsole\Api\FieldType;



/**
 * For float Vector3 only.
 * @see IntVector3FieldType for integer Vector3.
 * @implements FieldType<Vector3>
 */
final class Vector3FieldType implements FieldType {
    /** @var CompoundSubfield<mixed, float> */
    private CompoundSubfield $x, $y, $z;

    /**
     * All bounds are inclusive.
     */
    public function __construct(
        public ?float $minX = null,
        public ?float $minY = null,
        public ?float $minZ = null,
        public ?float $maxX = null,
        public ?float $maxY = null,
        public ?float $maxZ = null,
    ) {
        $this->x = new CompoundSubfield("x", "main-types-x", new FloatFieldType(min: $minX, max: $maxX));
        $this->y = new CompoundSubfield("y", "main-types-y", new FloatFieldType(min: $minY, max: $maxY));
        $this->z = new CompoundSubfield("z", "main-types-z", new FloatFieldType(min: $minZ, max: $maxZ));
    }

    public function serializeType() : array {
        return [
            "type" => "compound",
            "fields" => [
                $this->x->serializeType(),
                $this->y->serializeType(),
                $this->z->serializeType(),
            ]
        ];
    }

    public function serializeValue($value) : mixed {
        return [
            "x" => $value->x,
            "y" => $value->y,
            "z" => $value->z,
        ];
    }
}