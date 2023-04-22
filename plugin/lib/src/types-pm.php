<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Lib;

use pocketmine\math\Vector3;
use SOFe\WebConsole\Api\FieldType;

// Wrapper field types that project PocketMine data types to webapp field types.

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
        public ?float $minX,
        public ?float $minY,
        public ?float $minZ,
        public ?float $maxX,
        public ?float $maxY,
        public ?float $maxZ,
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

/**
 * For integer Vector3 only.
 * @see Vector3FieldType for float Vector3.
 * @implements FieldType<Vector3>
 */
final class IntVector3FieldType implements FieldType {
    /** @var CompoundSubfield<mixed, int> */
    private CompoundSubfield $x, $y, $z;

    /**
     * All bounds are inclusive.
     */
    public function __construct(
        public ?int $minX,
        public ?int $minY,
        public ?int $minZ,
        public ?int $maxX,
        public ?int $maxY,
        public ?int $maxZ,
    ) {
        $this->x = new CompoundSubfield("x", "main-types-x", new IntFieldType(min: $minX, max: $maxX));
        $this->y = new CompoundSubfield("y", "main-types-y", new IntFieldType(min: $minY, max: $maxY));
        $this->z = new CompoundSubfield("z", "main-types-z", new IntFieldType(min: $minZ, max: $maxZ));
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
            "x" => $value->getFloorX(),
            "y" => $value->getFloorY(),
            "z" => $value->getFloorZ(),
        ];
    }
}
