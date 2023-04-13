<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Internal;

use Closure;
use Exception;
use Generator;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Channel;
use SOFe\AwaitGenerator\Loading;
use SOFe\AwaitGenerator\Traverser;
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

            if ($name === null) {
                /** @var Closure(string): bool $fieldFilter */
                $fieldFilter = fn() => true;
                if (($fields = $request->address->query["fields"] ?? null) !== null) {
                    $fieldFilter = fn(string $id) => in_array($id, $fields, true);
                }

                if ($isWatch) {
                    return new HttpResponse(
                        "HTTP/1.0",
                        "200 OK",
                        new HttpHeaders([
                            "Cache-Control" => "no-cache",
                            "Content-Type" => "text/event-stream; charset=utf-8",
                        ]),
                        Traverser::fromClosure(function() use ($objectDef, $fieldFilter, $limit) {
                            yield from $this->watch($objectDef, $fieldFilter, $limit);
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
        }

        return new HttpResponse("HTTP/1.0", "405 Method Not Allowed", new HttpHeaders, Traverser::fromClosure(function() {
            yield "405 Method Not Allowed" => Traverser::VALUE;
        }));
    }

    /**
     * @template I
     * @param ObjectDef<I> $objectDef
     * @param Closure(string): bool $fieldFilter
     * @return Generator<mixed, mixed, mixed, void>
     */
    private function watch(ObjectDef $objectDef, Closure $fieldFilter, ?int $limit) : Generator {
        $channel = new Channel;

        Await::f2c(function() use ($objectDef, $fieldFilter, $limit, $channel) {
            while (true) {
                $watch = new WatchHandler($objectDef, $limit, $fieldFilter, $channel);
                yield from $watch->watchObjects();
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
        $list = $objectDef->desc->list();

        try {
            if ($limit !== null) {
                $limit -= 1;
                if ($limit < 0) {
                    return;
                }
            }

            while (yield from $list->next($object)) {
                $item = yield from AccessorUtil::populateObjectFields($objectDef, $object, $fieldFilter);

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

final class RestartWatch extends Exception {
}

final class AccessorUtil {
    /**
     * @template I
     * @param ObjectDef<I> $objectDef
     * @param I $identity
     * @return Generator<mixed, mixed, mixed, array<string, mixed>>
     */
    public static function populateObjectFields(ObjectDef $objectDef, $identity, Closure $fieldFilter) : Generator {
        $item = [
            "_name" => $objectDef->desc->name($identity),
        ];

        $futures = [];
        foreach ($objectDef->fields as $field) {
            if ($fieldFilter($field->path)) {
                $futures[] = (function() use ($field, &$item, $identity) {
                    $fieldParts = explode(".", $field->path);
                    $rawValue = yield from $field->desc->get($identity);
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
}
