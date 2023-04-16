<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Internal;

use Closure;
use Generator;
use RuntimeException;
use libs\_85f6d346dd7f97fb\SOFe\AwaitGenerator\Await;
use libs\_85f6d346dd7f97fb\SOFe\AwaitGenerator\Channel;
use libs\_85f6d346dd7f97fb\SOFe\AwaitGenerator\Loading;
use libs\_85f6d346dd7f97fb\SOFe\AwaitGenerator\PubSub;
use libs\_85f6d346dd7f97fb\SOFe\AwaitGenerator\Traverser;
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

        $initialObject = yield from self::populateObjectFields($this->objectDef, $identity, $this->fieldFilter, fn($field) => self::getTraverserOnce($watches[$field->path]));
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
     * @template V
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
     * @template I
     * @param ObjectDef<I> $objectDef
     * @param I $identity
     * @param Closure(FieldDef<I, mixed>): Generator<mixed, mixed, mixed, mixed>
     * @return Generator<mixed, mixed, mixed, array<string, mixed>>
     */
    public static function populateObjectFields(ObjectDef $objectDef, $identity, Closure $fieldFilter, Closure $fieldGetter) : Generator {
        $item = [
            "_name" => $objectDef->desc->name($identity),
        ];

        $futures = [];
        foreach ($objectDef->fields as $field) {
            if ($fieldFilter($field->path)) {
                $futures[] = (function() use ($field, &$item, $identity, $fieldGetter) {
                    $fieldParts = explode(".", $field->path);
                    $rawValue = yield from $fieldGetter($field);
                    $fieldValue = $field->type->serializeValue($rawValue);

                    /** @var array<string, mixed> $ptr */
                    $ptr = &$item;
                    foreach ($fieldParts as $fieldPart) {
                        if (!isset($ptr[$fieldPart])) {
                            $ptr[$fieldPart] = [];
                        }
                        $ptr = &$ptr[$fieldPart];
                    }
                    $ptr = $fieldValue;
                    unset($ptr);
                })();
            }
        }

        yield from Await::all($futures);

        return $item;
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