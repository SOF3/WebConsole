<?php

declare(strict_types=1);

namespace libs\_37f8d49eb6299cb1\SOFe\WebConsole\Lib;

use pocketmine\math\Vector3;
use SOFe\WebConsole\Api\FieldType;
















































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
        public ?int $minX = null,
        public ?int $minY = null,
        public ?int $minZ = null,
        public ?int $maxX = null,
        public ?int $maxY = null,
        public ?int $maxZ = null,
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