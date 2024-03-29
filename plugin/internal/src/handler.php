<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Internal;

use Closure;
use Generator;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitGenerator\Loading;
use SOFe\AwaitGenerator\PubSub;
use SOFe\AwaitGenerator\Traverser;
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

final class Handler {
    public function __construct(
        private Registry $registry,
    ) {
    }

    /**
     * @return Generator<mixed, mixed, mixed, HttpResponse>
     */
    public function handle(HttpRequest $request) : Generator {
        if ($request->address->method === "GET") {
            if ($request->address->path === "/discovery") {
                return $this->discovery();
            }

            if ($request->address->path === "/locales") {
                return $this->localeList();
            }

            if (str_ends_with($request->address->path, ".ftl")) {
                return $this->getLocale($request);
            }
        }

        // Syntax: group/kind or group/kind/name, where name has arbitrary characters as long as it does not contain `?`

        return yield from $this->objectRequest($request);
    }

    private function objectRequest(HttpRequest $request) : Generator {
        false && yield;

        $parts = explode("/", ltrim($request->address->path, "/"), 3);
        if (count($parts) === 1) {
            return $this->notFound();
        }

        [$group, $kind] = $parts;
        $name = $parts[2] ?? null;

        $objectDef = $this->registry->objectKinds["$group/$kind"] ?? null;
        if ($objectDef === null) {
            return $this->notFound();
        }

        if ($request->address->method === "GET") {
            $isWatch = isset($request->address->query["watch"]);
            $limit = (int) $request->address->getQueryOnce("limit", 0);
            if ($limit === 0) {
                $limit = null;
            }

            /** @var Closure(string): bool $fieldFilter */
            $fieldFilter = fn() => true;
            if (($fields = $request->address->query["fields"] ?? null) !== null) {
                $fieldFilter = fn(string $id) => in_array($id, $fields, true);
            }

            if ($name === null) {
                if ($isWatch) {
                    return new HttpResponse(
                        "HTTP/1.0",
                        "200 OK",
                        new HttpHeaders([
                            "Cache-Control" => "no-cache",
                            "Content-Type" => "text/event-stream; charset=utf-8",
                        ]),
                        Traverser::fromClosure(function() use ($objectDef, $fieldFilter, $limit) {
                            yield from $this->watchList($objectDef, $fieldFilter, $limit);
                        }),
                    );
                } else {
                    return new HttpResponse(
                        "HTTP/1.0",
                        "200 OK",
                        new HttpHeaders([
                            "Cache-Control" => "no-cache",
                        ]),
                        Traverser::fromClosure(function() use ($objectDef, $fieldFilter, $limit) {
                            yield from $this->list($objectDef, $fieldFilter, $limit);
                        }),
                    );
                }
            }

            if ($isWatch) {
                $identity = yield from $objectDef->desc->get($name);
                if ($identity === null) {
                    return $this->notFound();
                }

                return new HttpResponse(
                    "HTTP/1.0",
                    "200 OK",
                    new HttpHeaders([
                        "Cache-Control" => "no-cache",
                        "Content-Type" => "text/event-stream; charset=utf-8",
                    ]),
                    Traverser::fromClosure(function() use ($objectDef, $fieldFilter, $identity) {
                        yield from $this->watchSingle($objectDef, $fieldFilter, $identity);
                    }),
                );
            }
        }

        return new HttpResponse("HTTP/1.0", "405 Method Not Allowed", new HttpHeaders, Traverser::fromClosure(function() {
            yield "405 Method Not Allowed" => Traverser::VALUE;
        }));
    }

    /**
     * @template I
     * @param ObjectDef<I> $objectDef
     * @param Closure(string): bool $fieldFilter
     * @param I $identity
     */
    private function watchSingle(ObjectDef $objectDef, Closure $fieldFilter, $identity) : Generator {
        $channel = new Channel;

        foreach ($objectDef->fields as $field) {
            if ($fieldFilter($field->path)) {
                Await::f2c(function() use ($field, $identity, $channel) {
                    $traverser = $field->desc->watch($identity);
                    while (yield from $traverser->next($value)) {
                        $channel->sendWithoutWait([
                            "event" => "Update",
                            "field" => $field->path,
                            "value" => $field->type->serializeValue($value),
                        ]);
                    }
                });
            }
        }

        while (true) {
            $message = yield from $channel->receive();

            yield "data: " => Traverser::VALUE;
            yield $this->jsonEncode($message) => Traverser::VALUE;
            yield "\n\n" => Traverser::VALUE;
        }
    }

    /**
     * @template I
     * @param ObjectDef<I> $objectDef
     * @param Closure(string): bool $fieldFilter
     * @return Generator<mixed, mixed, mixed, void>
     */
    private function watchList(ObjectDef $objectDef, Closure $fieldFilter, ?int $limit) : Generator {
        $channel = new Channel;

        Await::f2c(function() use ($objectDef, $fieldFilter, $limit, $channel) {
            while (true) {
                // each iteration indicates one cycle of re-listing everything.
                $watch = new WatchHandler($objectDef, $limit, $fieldFilter, $channel);
                try {
                    yield from $watch->watchObjects();
                } catch(RestartAddWatch $e) {
                    continue;
                }

                break;
            }
        });

        while (true) {
            $message = yield from $channel->receive();

            yield "data: " => Traverser::VALUE;
            yield $this->jsonEncode($message) => Traverser::VALUE;
            yield "\n\n" => Traverser::VALUE;
        }
    }

    /**
     * @template I
     * @param ObjectDef<I> $objectDef
     * @param Closure(string): bool $fieldFilter
     * @return Generator<mixed, mixed, mixed, void>
     */
    private function list(ObjectDef $objectDef, Closure $fieldFilter, ?int $limit) {
        $list = $objectDef->desc->watchAdd(true, $limit);

        try {
            if ($limit !== null) {
                $limit -= 1;
                if ($limit < 0) {
                    return;
                }
            }

            while (yield from $list->next($object)) {
                $item = yield from self::populateObjectFields($objectDef, $object, $fieldFilter, fn($field) => $field->desc->get($object));

                yield $this->jsonEncode($item) => Traverser::VALUE;
                yield "\n" => Traverser::VALUE;
            }
        } finally {
            $e = yield from $list->interrupt();
            if ($e !== null) {
                throw $e;
            }
        }
    }

    /**
     * @template I
     * @param ObjectDef<I> $objectDef
     * @param I $identity
     * @param Closure(string): bool $fieldFilter
     * @param Closure(FieldDef<I, mixed>): Generator<mixed, mixed, mixed, mixed> $fieldGetter
     * @return Generator<mixed, mixed, mixed, array<string, mixed>>
     */
    public static function populateObjectFields(ObjectDef $objectDef, $identity, Closure $fieldFilter, Closure $fieldGetter) : Generator {
        $item = [
            "_name" => $objectDef->desc->name($identity),
        ];

        $futures = [];
        foreach ($objectDef->fields as $field) {
            if ($fieldFilter($field->path)) {
                $futures[] = (function() use ($field, &$item, $fieldGetter) {
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

    private function discovery() : HttpResponse {
        $output = [
            "groups" => [],
            "apis" => [],
        ];
        foreach ($this->registry->groups as $group) {
            $output["groups"][] = [
                "id" => $group->id,
                "display_name" => $group->displayName,
                "display_priority" => $group->displayPriority,
            ];
        }
        foreach ($this->registry->objectKinds as $kind) {
            $fields = [];
            foreach ($kind->fields as $field) {
                $fields[] = [
                    "path" => $field->path,
                    "display_name" => $field->displayName,
                    "metadata" => $field->getMetadata(),
                    "type" => $field->type->serializeType(),
                ];
            }

            $output["apis"][] = [
                "kind" => $kind->kind,
                "display_name" => $kind->displayName,
                "metadata" => $kind->getMetadata(),
                "group" => $kind->group,
                "fields" => $fields,
            ];
        }

        return $this->okJson($output);
    }

    private function localeList() : HttpResponse {
        return $this->okJson(array_keys($this->registry->fluentLocales));
    }

    private function getLocale(HttpRequest $request) : HttpResponse {
        $locale = substr($request->address->path, 1, strlen($request->address->path) - 5);
        if (!isset($this->registry->fluentLocales[$locale])) {
            return $this->notFound();
        }

        $buf = $this->registry->fluentLocales[$locale]->fluent;

        $headers = new HttpHeaders([
            "Content-Type" => "text/plain",
            "Content-Length" => (string) strlen($buf),
        ]);
        return new HttpResponse("HTTP/1.0", "200 OK", $headers, Traverser::fromClosure(function() use ($buf) {
            yield $buf => Traverser::VALUE;
        }));
    }

    private function okJson(mixed $value) : HttpResponse {
        $buf = $this->jsonEncode($value);
        if ($buf === false) {
            return $this->internalError("Encode JSON result: " . json_last_error_msg());
        }

        $headers = new HttpHeaders([
            "Content-Type" => "application/json",
            "Content-Length" => (string) strlen($buf),
        ]);
        return new HttpResponse("HTTP/1.0", "200 OK", $headers, Traverser::fromClosure(function() use ($buf) {
            yield $buf => Traverser::VALUE;
        }));
    }

    private function jsonEncode(mixed $value) : string|false {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function internalError(string $error) : HttpResponse {
        return new HttpResponse("HTTP/1.0", "500 Internal Server Error", new HttpHeaders, Traverser::fromClosure(function() use ($error) {
            yield $error => Traverser::VALUE;
        }));
    }

    private function notFound() : HttpResponse {
        return new HttpResponse("HTTP/1.0", "404 Not Found", new HttpHeaders, Traverser::fromClosure(function() {
            yield "404 Not Found" => Traverser::VALUE;
        }));
    }
}

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
                    "value" => $field->type->serializeValue($newValue),
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
