<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Exception;
use Generator;
use libs\_85f6d346dd7f97fb\SOFe\AwaitGenerator\Traverser;
use function sprintf;






















































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
     * Watches the addition of all objects.
     * All existing objects should be treated as an addition.
     *
     * The traverser may be suspended for a long time during a value yield.
     * Implementors should take care of potential timeout/invalidation when the control is returned.
     * The value yield and suspension points may also interrupt the call with an `InterruptException`,
     * in which case the implementation should close all backing handles, queries, etc.
     *
     * The implementor may throw a `RestartAddWatch` to request re-listing all initial objects.
     * In this case, the caller should clear all previous known objects and call `watchAdd` again.
     *
     * @param bool $listOnly If true, the traverser should return after the initial list is complete.
     *                       If false, the traverser should continue notifying for new additions.
     * @param int|null $limit If non null, the caller is only interested in `$limit` objects at a time.
     *                        Subsequent objects may still be fetched if some of the previous objects are removed.
     *                        This is just an optimization hint.
     * @return Traverser<I>
     */
    public function watchAdd(bool $listOnly, ?int $limit) : Traverser;

    /**
     * Returns a future that resolves when the object is removed.
     *
     * The HTTP watch implementation invokes this method for every item yielded by `watchAdd`.
     *
     * Suspension points may be interrupted,
     * in which case the implementation should clean up all backing handles, queries, etc.
     *
     * @param I $object
     * @return Generator<mixed, mixed, mixed, void>
     */
    public function watchRemove($object) : Generator;

    /**
     * Fetches an object given its name.
     *
     * @return Generator<mixed, mixed, mixed, ?I>
     */
    public function get(string $name) : Generator;
}