<?php

declare(strict_types=1);

namespace SOFe\WebConsole;

use Closure;
use Generator;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Traverser;
use function array_keys;
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
            $withExisting = isset($request->address->query["withExisting"]);

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
                        Traverser::fromClosure(function() use ($objectDef, $fieldFilter, $withExisting) {
                            yield from $this->watch($objectDef, $fieldFilter, $withExisting);
                        }),
                    );
                } else {
                    return new HttpResponse(
                        "HTTP/1.0",
                        "200 OK",
                        new HttpHeaders([
                            "Cache-Control" => "no-cache",
                        ]),
                        Traverser::fromClosure(function() use ($objectDef, $fieldFilter) {
                            yield from $this->list($objectDef, $fieldFilter);
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
    private function watch(ObjectDef $objectDef, Closure $fieldFilter, bool $withExisting) : Generator {
        $list = $objectDef->desc->watch($withExisting);

        try {
            while (yield from $list->next($event)) {
                yield "data: " => Traverser::VALUE;
                if ($event instanceof AddObjectEvent) {
                    $item = yield from $this->populateObjectFields($objectDef, $event->item, $fieldFilter);
                    yield $this->jsonEncode([
                        "event" => "Added",
                        "object" => $item,
                    ]) => Traverser::VALUE;
                } else {
                    yield $this->jsonEncode([
                        "event" => "Removed",
                        "name" => $objectDef->desc->name($event->item),
                    ]) => Traverser::VALUE;
                }

                yield "\n\n" => Traverser::VALUE;
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
     * @param Closure(string): bool $fieldFilter
     * @return Generator<mixed, mixed, mixed, void>
     */
    private function list(ObjectDef $objectDef, Closure $fieldFilter) {
        $list = $objectDef->desc->list();

        try {
            while (yield from $list->next($object)) {
                $item = yield from $this->populateObjectFields($objectDef, $object, $fieldFilter);

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
     * @param I $object
     * @return Generator<mixed, mixed, mixed, array<string, mixed>>
     */
    private function populateObjectFields(ObjectDef $objectDef, $object, Closure $fieldFilter) : Generator {
        $item = [
            "_name" => $objectDef->desc->name($object),
        ];

        $futures = [];
        foreach ($objectDef->fields as $field) {
            if ($fieldFilter($field->path)) {
                $futures[] = (function() use ($field, &$item, $object) {
                    $fieldParts = explode(".", $field->path);
                    $rawValue = yield from $field->desc->get($object);
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
                    "metadata" => $field->metadata,
                    "type" => $field->type->serializeType(),
                ];
            }

            $output["apis"][] = [
                "kind" => $kind->kind,
                "display_name" => $kind->displayName,
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
