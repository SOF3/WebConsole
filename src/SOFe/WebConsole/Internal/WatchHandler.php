<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Internal;

use Closure;
use Generator;
use RuntimeException;
use libs\_05d4a3a3240f542a\SOFe\AwaitGenerator\Await;
use libs\_05d4a3a3240f542a\SOFe\AwaitGenerator\Channel;
use libs\_05d4a3a3240f542a\SOFe\AwaitGenerator\Loading;
use libs\_05d4a3a3240f542a\SOFe\AwaitGenerator\PubSub;
use libs\_05d4a3a3240f542a\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RestartAddWatch;

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

    /** @var PubSub<null> */
    private PubSub $nextRemoval;

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
        $this->nextRemoval = new PubSub;
    }

    /**
     * @return Generator<mixed, mixed, mixed, void>
     */
    public function watchObjects() {
        $this->channel->sendWithoutWait([
            "event" => "Clear",
        ]);

        $watch = $this->objectDef->desc->watchAdd(false, $this->limit);

        try {
            while (yield from $watch->next($identity)) {
                $name = $this->objectDef->desc->name($identity);

                yield from $this->handleAddEvent($identity, $name);

                $sub = $this->nextRemoval->subscribe();
                try {
                    while ($this->limit !== null && count($this->objectSet) >= $this->limit) {
                        yield from $sub->next($_);
                    }
                } finally {
                    yield from $sub->interrupt();
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
        if (isset($this->objectSet[$name])) {
            throw new RuntimeException("Object descriptor watch() yields AddObjectEvent for \"$name\" twice");
        }

        /** @var array<string, Traverser<mixed>> $watches */
        $watches = [];
        foreach ($this->objectDef->fields as $path => $field) {
            $watches[$path] = $field->desc->watch($identity);
        }

        $initialObject = yield from Handler::populateObjectFields($this->objectDef, $identity, $this->fieldFilter, fn($field) => self::getTraverserOnce($watches[$field->path]));
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

        $this->objectSet[$name] = $cancelFn;

        foreach ($this->objectDef->fields as $path => $field) {
            // TODO throw interrupt
            Await::g2c($this->watchObjectField($field, $identity, $cancel, $watches[$path]));
        }

        // TODO throw interrupt
        Await::g2c($this->watchObjectRemoval($identity, $name));
    }

    /**
     * @param I $identity
     */
    private function watchObjectRemoval($identity, string $name) : Generator {
        yield from $this->objectDef->desc->watchRemove($identity);

        if (!isset($this->objectSet[$name])) {
            throw new RuntimeException("Object descriptor watchRemove() returns after objectSet no longer contains \"$name\"");
        }

        $this->objectSet[$name]();
        unset($this->objectSet[$name]);

        $this->channel->sendWithoutWait([
            "event" => "Removed",
            "name" => $name,
        ]);

        $this->nextRemoval->publish(null);
    }

    /**
     * @param I $identity
     * @param FieldDef<I, mixed> $field
     * @param Loading<void> $cancel
     * @param Traverser<mixed> $watch
     */
    private function watchObjectField(FieldDef $field, $identity, Loading $cancel, Traverser $watch) : Generator {
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
     * @template T
     * @param Traverser<T> $traverser
     * @return Generator<mixed, mixed, mixed, T>
     */
    private static function getTraverserOnce(Traverser $traverser) : Generator {
        if (yield from $traverser->next($item)) {
            return $item;
        }

        throw new RuntimeException("field watch traverser returned without generating any values");
    }
}