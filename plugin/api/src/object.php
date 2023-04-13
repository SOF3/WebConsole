<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use SOFe\AwaitGenerator\Traverser;
use function sprintf;

final class GroupDef {
    public function __construct(
        public string $id,
        public string $displayName,
        public int $displayPriority,
    ) {
    }
}

/**
 * @template I The "identity" type, which is the type that the object lister provides to field suppliers.
 */
final class ObjectDef {
    /** @var array<string, FieldDef<I, mixed>> */
    public array $fields = [];


    /**
     * @param ObjectDesc<I> $desc
     * @param ObjectMetadata[] $metadata
     */
    public function __construct(
        public string $group,
        public string $kind,
        public string $displayName,
        public ObjectDesc $desc,
        public array $metadata,
    ) {
    }

    public function id() : string {
        return sprintf("%s/%s", $this->group, $this->kind);
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

interface ObjectMetadata {
    /**
     * @param array<string, mixed> $metadata
     */
    public function apply(array &$metadata) : void;
}

/**
 * @template I
 */
interface ObjectDesc {
    /**
     * Converts an object to its name.
     *
     * @param I $object
     */
    public function name($object) : string;

    /**
     * Lists all possible objects.
     *
     * The order of traversal does not need to be sorted,
     * but it *should* try to be stable for better user experience.
     *
     * The returned traverser may be interrupted with an InterruptException,
     * in which case any backing queries should be terminated.
     *
     * @return Traverser<I>
     */
    public function list() : Traverser;

    /**
     * Watches the addition and removal of objects.
     * It should initially yield all creation events first.
     *
     * The traverser yields an ObjectEvent indicating the addition or removal of an event.
     *
     * The returned traverser may be interrupted with an InterruptException,
     * in which case any backing queries should be terminated.
     *
     * @param int|null $limit If non null, he caller only handles the first `$limit` AddObjectEvents,
     *                        so sending extra AddObjectEvents is pointless.
     * @return Traverser<AddObjectEvent<I>|RemoveObjectEvent<I>>
     */
    public function watch(?int $limit) : Traverser;

    /**
     * Fetches an object given its name.
     *
     * @return Generator<mixed, mixed, mixed, ?I>
     */
    public function get(string $name) : Generator;
}

/**
 * @template I
 */
final class AddObjectEvent {
    /**
     * @param I $item
     */
    public function __construct(public $item) {
    }
}

/**
 * @template I
 */
final class RemoveObjectEvent {
    /**
     * @param I $item
     */
    public function __construct(public $item) {
    }
}
