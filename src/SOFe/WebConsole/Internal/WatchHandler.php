<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Internal;

use Closure;
use Exception;
use Generator;
use RuntimeException;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Await;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Channel;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Loading;
use libs\_f00b9901125756a3\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\AddObjectEvent;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RemoveObjectEvent;

use function array_keys;
use function assert;
use function count;
use function explode;
use function in_array;
use function json_encode;
use function json_last_error_msg;
use function ltrim;
use function str_ends_with;
use function strlen;
use function substr;












































































































































































































































/**
 * @template I
 */
final class WatchHandler {
    /** @var array<string, Closure(): void> */
    private array $objectSet = [];

    /**
     * @param ObjectDef<I> $objectDef
     * @param Closure(string): bool $fieldFilter
     * @param Channel<mixed> $channel
     */
    public function __construct(
        private ObjectDef $objectDef,
        private ?int $limit,
        private Closure $fieldFilter,
        private Channel $channel,
    ) {
    }

    /**
     * @return Generator<mixed, mixed, mixed, void>
     */
    public function watchObjects() {
        $watch = $this->objectDef->desc->watch($this->limit);

        try {
            while (yield from $watch->next($event)) {
                $identity = $event->item;
                $name = $this->objectDef->desc->name($identity);

                if ($event instanceof AddObjectEvent) {
                    yield from $this->handleAddEvent($identity, $name);
                } elseif ($event instanceof RemoveObjectEvent) {
                    try {
                        yield from $this->handleRemoveEvent($identity, $name);
                    } catch(RestartWatch $e) {
                        return;
                    }
                }
            }
        } finally {
            $e = yield from $watch->interrupt();
            if ($e !== null) {
                throw $e;
            }
        }
    }

    /**
     * @param I $identity
     */
    private function handleAddEvent($identity, string $name) : Generator {
        if ($this->limit !== null && count($this->objectSet) >= $this->limit) {
            return;
        }

        if (isset($this->objectSet[$name])) {
            throw new RuntimeException("Object descriptor watch() yields AddObjectEvent for \"$name\" twice");
        }

        $initialObject = yield from AccessorUtil::populateObjectFields($this->objectDef, $identity, $this->fieldFilter);
        $this->channel->sendWithoutWait([
            "event" => "Added",
            "item" => $initialObject,
        ]);

        /** @var ?Closure(): void $cancelFn */
        $cancelFn = null;
        $cancel = new Loading(function() use (&$cancelFn) {
            return yield from Await::promise(function($resolve) use (&$cancelFn) {
                $cancelFn = $resolve;
            });
        });
        assert($cancelFn !== null, "Loading closure and Await::promise initial are called synchronously");

        foreach ($this->objectDef->fields as $field) {
            Await::g2c($this->watchObjectField($field, $identity, $cancel));
        }
        $this->objectSet[$name] = $cancelFn;
    }

    /**
     * @param I $identity
     * @param FieldDef<I, mixed> $field
     * @param Loading<void> $cancel
     */
    private function watchObjectField(FieldDef $field, $identity, Loading $cancel) : Generator {
        $watch = $field->desc->watch($identity);

        try {
            while (true) {
                [$which, $hasNext] = yield from Await::safeRace([
                    $cancel->get(),
                    $watch->next($newValue),
                ]);

                if ($which === 0) {
                    // canceled
                    return;
                }

                if (!$hasNext) {
                    return;
                }

                $this->channel->sendWithoutWait([
                    "event" => "FieldUpdate",
                    "name" => $this->objectDef->desc->name($identity),
                    "field" => $field->path,
                    "value" => $newValue,
                ]);
            }
        } finally {
            $e = yield from $watch->interrupt();
            if ($e !== null) {
                throw $e;
            }
        }
    }

    /**
     * @param I $identity
     */
    private function handleRemoveEvent($identity, string $name) : Generator {
        false && yield;

        if (!isset($this->objectSet[$name])) {
            throw new RuntimeException("Object descriptor watch() yields RemoveObjectEvent for non-added object \"$name\"");
        }

        if ($this->limit !== null) {
            throw new RestartWatch;
        }

        $this->objectSet[$name]();
        unset($this->objectSet[$name]);

        $this->channel->sendWithoutWait([
            "event" => "Removed",
            "name" => $name,
        ]);
    }
}