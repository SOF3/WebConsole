<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Internal;

use Closure;
use Exception;
use Generator;
use RuntimeException;
use libs\_67fd2f9d3627d2f8\SOFe\AwaitGenerator\Await;
use libs\_67fd2f9d3627d2f8\SOFe\AwaitGenerator\Channel;
use libs\_67fd2f9d3627d2f8\SOFe\AwaitGenerator\Loading;
use libs\_67fd2f9d3627d2f8\SOFe\AwaitGenerator\Traverser;
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