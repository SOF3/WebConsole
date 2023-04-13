<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use SOFe\AwaitGenerator\Traverser;
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
     */
    public function __construct(
        public string $objectGroup,
        public string $objectKind,
        public string $path,
        public string $displayName,
        public FieldType $type,
        public array $metadata,
        public FieldDesc $desc,
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

interface FieldMetadata {
    /**
     * @param array<string, mixed> $metadata
     */
    public function apply(array &$metadata) : void;
}

/**
 * @template I
 * @template V
 */
interface FieldDesc {
    /**
     * @param I $identity
     * @return Generator<mixed, mixed, mixed, V>
     */
    public function get($identity) : Generator;

    /**
     * @param I $identity
     * @return Traverser<V>
     */
    public function watch($identity) : Traverser;
}

/**
 * @template V
 */
interface FieldType {
    /**
     * @return array<string, mixed>
     */
    public function serializeType() : array;

    /**
     * @param V $value
     */
    public function serializeValue($value) : mixed;
}
