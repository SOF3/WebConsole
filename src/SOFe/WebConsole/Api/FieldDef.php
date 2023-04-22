<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Closure;
use Generator;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Traverser;
use function sprintf;

/**
 * @template I the identifier type of the object
 * @template V the type of the object field
 */
final class FieldDef {
    /**
     * @param FieldType<V> $type
     * @param FieldMetadata[] $metadata
     * @param FieldDesc<I, V> $desc
     * @param ?MutableFieldDesc<I, V> $mutableDesc
     */
    public function __construct(
        public string $objectGroup,
        public string $objectKind,
        public string $path,
        public string $displayName,
        public FieldType $type,
        public FieldDesc $desc,
        public array $metadata = [],
        public ?MutableFieldDesc $mutableDesc = null,
    ) {
    }

    public function objectId() : string {
        return sprintf("%s/%s", $this->objectGroup, $this->objectKind);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata() : array {
        $metadata = [];
        foreach ($this->metadata as $datum) {
            $datum->apply($metadata);
        }
        return $metadata;
    }
}