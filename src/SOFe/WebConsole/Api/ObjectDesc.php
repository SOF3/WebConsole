<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Generator;
use libs\_fda4469118c2f4f9\SOFe\AwaitGenerator\Traverser;
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