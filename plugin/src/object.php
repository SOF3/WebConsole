<?php

declare(strict_types=1);

namespace SOFe\WebConsole;

use Closure;
use RuntimeException;

final class ObjectDef {
    /**
     * @param array<string, string> $metadata
     */
    public function __construct(
        public string $group,
        public string $kind,
        public array $metadata,
    ) {}

    public function id() : string {
        return sprintf("%s/%s", $this->group, $this->kind);
    }
}

/**
 * @template T
 */
final class ObjectStore {
    /** @var array<string, T> */
    private array $list = [];

    /** @var array<string, ObjectDetail<T>> */
    private array $details = [];

    public function __construct(
        public ObjectDef $def,
    ) {}

    public function add(string $id, $object) {
        $this->list[$id] = $object;
        $this->pushEvent($id);
    }

    public function delete(string $id) {
        if(!isset($this->list[$id])) {
            throw new RuntimeException("$id was not in the store");
        }

        unset($this->list[$id]);
        $this->pushEvent($id);
    }
}

/**
 * @template T
 * @template V
 */
final class ObjectDetail {
    /**
     * @param array<string, string> $metadata
     * @param ObjectDetailType<V> $type
     * @param Closure(T): Traverser<V> $supplier
     */
    public function __construct(
        public string $group,
        public string $id,
        public ObjectDetailType $type,
        public array $metadata,
        public Closure $supplier,
    ) {}
}

/**
 * @template T
 */
final class ObjectDetailType {}
