<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Internal;

use Closure;
use Generator;
use RuntimeException;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Await;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Channel;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Loading;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\PubSub;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RestartAddWatch;

use function array_keys;
use function assert;
use function count;
use function explode;
use function in_array;
use function is_array;
use function json_decode;
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
                return yield from $this->objectListWatch($request, $objectDef, $isWatch, $limit);
            }

            return yield from $this->objectGet($request, $objectDef, $isWatch);
        }

        if ($request->address->method === "PATCH") {
            return yield from $this->objectPatch($request, $objectDef, $name);
        }

        return new HttpResponse("HTTP/1.0", "405 Method Not Allowed", new HttpHeaders, Traverser::fromClosure(function() {
            yield "405 Method Not Allowed" => Traverser::VALUE;
        }));
    }

    /**
     * @template I
     * @param ObjectDef<I> $objectDef
     * @return Generator<mixed, mixed, mixed, HttpResponse>
     */
    private function objectGet(HttpRequest $request, ObjectDef $objectDef, bool $isWatch) : Generator {
        false && yield;
        // TODO
        return $this->internalError("not yet implemented");
    }

    /**
     * @template I
     * @param ObjectDef<I> $objectDef
     * @return Generator<mixed, mixed, mixed, HttpResponse>
     */
    private function objectListWatch(HttpRequest $request, ObjectDef $objectDef, bool $isWatch, ?int $limit) : Generator {
        false && yield;

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
                    yield from $this->objectWatch($objectDef, $fieldFilter, $limit);
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
                    yield from $this->objectList($objectDef, $fieldFilter, $limit);
                }),
            );
        }
    }


    /**
     * @template I
     * @param ObjectDef<I> $objectDef
     * @param Closure(string): bool $fieldFilter
     * @return Generator<mixed, mixed, mixed, void>
     */
    private function objectWatch(ObjectDef $objectDef, Closure $fieldFilter, ?int $limit) : Generator {
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
    private function objectList(ObjectDef $objectDef, Closure $fieldFilter, ?int $limit) {
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
        /** @var array<string, mixed> $item */
        $item = [
            "_name" => $objectDef->desc->name($identity),
        ];

        $futures = [];
        foreach ($objectDef->fields as $field) {
            if ($fieldFilter($field->path)) {
                $futures[] = (function() use ($field, &$item, $fieldGetter) {
                    $rawValue = yield from $fieldGetter($field);
                    $fieldValue = $field->type->serializeValue($rawValue);
                    self::setRecursive($item, $field->path, $fieldValue);
                })();
            }
        }

        yield from Await::all($futures);

        return $item;
    }

    /**
     * @param array<string, mixed> $object
     */
    private static function setRecursive(array &$object, string $path, mixed $value) : void {
        $parts = explode(".", $path);

        /** @var array<string, mixed> $ptr */
        $ptr = &$object;
        foreach ($parts as $fieldPart) {
            if (!isset($ptr[$fieldPart])) {
                $ptr[$fieldPart] = [];
            }
            $ptr = &$ptr[$fieldPart];
        }
        $ptr = $value;
    }

    /**
     * @param array<string, mixed> $object
     * @return array{mixed, bool}
     */
    private static function getRecursive(array $object, string $path) : array {
        $parts = explode(".", $path);

        $ptr = $object;
        foreach ($parts as $fieldPart) {
            if (!isset($ptr[$fieldPart])) {
                return [null, false];
            }
            $ptr = $ptr[$fieldPart];
        }
        return [$ptr, true];
    }

    /**
     * @template I
     * @param ObjectDef<I> $objectDef
     * @return Generator<mixed, mixed, mixed, HttpResponse>
     */
    private function objectPatch(HttpRequest $request, ObjectDef $objectDef, ?string $name) : Generator {
        if ($name === null) {
            return $this->badRequest("patch requests must contain a name");
        }

        $object = json_decode($request->body, true);
        if (!is_array($object)) {
            return $this->badRequest("Patch body must be a JSON object");
        }

        $identity = yield from $objectDef->desc->get($name);
        if ($identity === null) {
            return $this->notFound();
        }

        $responses = [];
        foreach ($objectDef->fields as $field) {
            $desc = $field->mutableDesc;
            if ($desc !== null) {
                [$value, $exists] = self::getRecursive($object, $field->path);
                var_dump($field->path, $value, $exists);
                if ($exists) {
                    $response = yield from $desc->set($identity, $value);
                    $responses[$field->path] = $response->serialize();
                }
            }
        }

        return $this->okJson($responses);
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
                    "mutable" => $field->mutableDesc !== null,
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

    private function badRequest(string $error) : HttpResponse {
        return new HttpResponse("HTTP/1.0", "400 Bad Request", new HttpHeaders, Traverser::fromClosure(function() use ($error) {
            yield $error => Traverser::VALUE;
        }));
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