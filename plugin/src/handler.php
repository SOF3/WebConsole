<?php

declare(strict_types=1);

namespace SOFe\WebConsole;

use Generator;
use SOFe\AwaitGenerator\Traverser;

final class Handler {
    /**
     * @return Generator<mixed, mixed, mixed, HttpResponse>
     */
    public function handle(HttpRequest $request) : Generator {
        false && yield;
        // TODO authentication

        return match($request->address->path) {
            "/objects" => $this->discoverObjects($request),
            default => $this->notFound($request),
        };
    }

    private function notFound(HttpRequest $request) : HttpResponse {
        return new HttpResponse($request->address->httpVersion, "404 Not Found", new HttpHeaders, Traverser::fromClosure(function() {
            yield "404 Not Found" => Traverser::VALUE;
        }));
    }
}
