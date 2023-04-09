<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use RuntimeException;
use function array_key_exists;

final class FluentLocale {
    /** @var array<string, true> */
    public array $comps = [];
    public string $fluent = "";

    public function __construct(
        public string $locale,
    ) {
    }

    public function provide(string $comp, string $fluent) : void {
        if (array_key_exists($comp, $this->comps)) {
            throw new RuntimeException("Fluent bundle for \"$comp\" was provided multiple times");
        }
        $this->comps[$comp] = true;
        $this->fluent .= $fluent . "\n";
    }
}
