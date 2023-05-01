<?php

declare(strict_types=1);

namespace libs\_ee7e37d9654501e7\SOFe\WebConsole\Lib;

use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;
use RuntimeException;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\Registry;



/**
 * For float Position only.
 * @implements FieldType<Position>
 */
final class PositionFieldType implements FieldType {
    /** @var CompoundSubfield<mixed, float> */
    private CompoundSubfield $x, $y, $z;
    /** @var CompoundSubfield<mixed, ObjectRefFieldType<World>> */
    private CompoundSubfield $world;

    /**
     * All bounds are inclusive.
     */
    public function __construct(
        Registry $registry,
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
        $worldKind = $registry->getObjectDef(MainGroup::GROUP_ID, MainGroup::WORLD_KIND) ?? throw new RuntimeException("PositionFieldType cannot be constructed before World kind is registered");
        ;
        $this->world = new CompoundSubfield("world", "main-types-world", new ObjectRefFieldType($worldKind));
    }

    public function serializeType() : array {
        return [
            "type" => "compound",
            "fields" => [
                $this->x->serializeType(),
                $this->y->serializeType(),
                $this->z->serializeType(),
                $this->world->serializeType(),
            ]
        ];
    }

    public function serializeValue($value) : mixed {
        return [
            "x" => $value->x,
            "y" => $value->y,
            "z" => $value->z,
            "world" => $value->world->getFolderName(),
        ];
    }
}