<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Closure;
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
 * @template I
 * @template V
 */
interface MutableFieldDesc {
    /**
     * @param I $identity
     * @param V $value
     * @return Generator<mixed, mixed, mixed, FieldMutationResponse>
     */
    public function set($identity, $value) : Generator;
}

/**
 * @template I
 * @template V
 * @implements MutableFieldDesc<I, V>
 */
final class SimpleMutableFieldDesc implements MutableFieldDesc {
    /**
     * @param Closure(I, V): FieldMutationResponse $closure
     */
    public function __construct(
        public Closure $closure,
    ) {
    }

    public function set($identity, $value) : Generator {
        false && yield;
        return ($this->closure)($identity, $value);
    }
}

final class FieldMutationResponse {
    /**
     * @param FieldResponseMetadata[] $metadata
     */
    public function __construct(
        public bool $success,
        public ?string $errorCode,
        public ?string $i18nMessage,
        public array $metadata = [],
    ) {
    }

    public static function success() : self {
        return new self(true, null, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize() : array {
        return [
            "success" => $this->success,
            "errorCode" => $this->errorCode,
            "message" => $this->i18nMessage,
            "metadata" => $this->getMetadata(),
        ];
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

interface FieldResponseMetadata {
    /**
     * @param array<string, mixed> $metadata
     */
    public function apply(array &$metadata) : void;
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
