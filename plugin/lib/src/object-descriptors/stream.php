<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Lib;

use AssertionError;
use Generator;
use InvalidArgumentException;
use SOFe\AwaitGenerator\PubSub;
use SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\ObjectDesc;
use function array_key_first;
use function array_slice;
use function bcadd;
use function count;
use function dechex;
use function str_pad;
use function strlen;

/**
 * @template I
 * @implements ObjectDesc<StreamingObject<I>>
 */
final class StreamingObjectDesc implements ObjectDesc {
    private string $nextId = "1";
    /** @var array<string, StreamingObject<I>> */
    private array $data = [];

    /** @var PubSub<StreamingObject<I>> */
    private PubSub $additions;
    /** @var IndexedPubSub<null> */
    private IndexedPubSub $removals;

    public function __construct(
        private int $limit,
    ) {
        if ($limit <= 0) {
            throw new InvalidArgumentException("$limit <= 0");
        }

        $this->additions = new PubSub;
        $this->removals = new IndexedPubSub;
    }

    private function generateId() : string {
        $id = $this->nextId;
        $this->nextId = bcadd($id, "1");

        $length = strlen($id);
        $lengthString = dechex($length);
        $lengthPadded = str_pad($lengthString, 16, "0", STR_PAD_LEFT);

        // Due to the padding, $lengthPadded is automatically natural-ordered.
        // Within the same length, the ID values are also automatically natural-ordered.

        return "$lengthPadded,$id";
    }

    /**
     * @param I $rawObject
     */
    public function push($rawObject) : void {
        $name = $this->generateId();

        $object = new StreamingObject($name, $rawObject);

        if (count($this->data) > $this->limit) {
            $oldName = array_key_first($this->data);
            if ($oldName === null) {
                throw new AssertionError("count(\$this->data) > \$this->limit > 0");
            }

            unset($this->data[$oldName]);

            $this->removals->publish($oldName, null);
        }

        $this->data[$name] = $object;

        $this->additions->publish($object);
    }

    public function name($object) : string {
        return $object->name;
    }

    public function get(string $name) : Generator {
        false && yield;
        return $this->data[$name] ?? null;
    }

    public function watchAdd(bool $listOnly, ?int $limit) : Traverser {
        return Traverser::fromClosure(function() use ($listOnly, $limit) {
            $initial = $this->data;
            if ($limit !== null && count($initial) > $limit) {
                // we always want the last $limit items
                $initial = array_slice($initial, -$limit);
            }

            // create subscriber before any suspension points
            // to avoid losing messages created between now and last initial message flush.
            if (!$listOnly) {
                $sub = $this->additions->subscribe();
            }

            try {
                foreach ($initial as $item) {
                    yield $item => Traverser::VALUE;
                }

                if ($listOnly) {
                    return;
                }

                while (yield from $sub->next($item)) {
                    yield $item => Traverser::VALUE;
                }
            } finally {
                if (isset($sub)) {
                    yield from $sub->interrupt();
                }
            }
        });
    }

    public function watchRemove($object) : Generator {
        yield from $this->removals->watchOnce($object->name);
    }
}

/**
 * @template I
 */
final class StreamingObject {
    /**
     * @param I $object
     */
    public function __construct(
        public string $name,
        public $object,
    ) {
    }
}

/**
 * @template I
 */
final class QueueEvent {
    /**
     * @param I $object
     */
    public function __construct(
        public bool $add,
        public string $name,
        public $object,
    ) {
    }
}
