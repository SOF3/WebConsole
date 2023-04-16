<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Lib\Metadata;

use SOFe\WebConsole\Api\ObjectMetadata;

/**
 * On WebConsole web app, hide the name from display.
 *
 * This is used when the name is meaningless and only a WebConsole internal identifier.
 */
final class HideName implements ObjectMetadata {
    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/hide-name"] = true;
    }
}

/**
 * On WebConsole web app, sort the name by descending order by default.
 */
final class DescendingSort implements ObjectMetadata {
    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/desc-name"] = true;
    }
}

/**
 * On WebConsole web app, determine the default display mode.
 */
final class DefaultDisplayMode implements ObjectMetadata {
    public static function cards() : self {
        return new self("cards");
    }

    public static function table() : self {
        return new self("table");
    }

    private function __construct(
        public string $mode,
    ) {
    }

    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/default-display-mode"] = $this->mode;
    }
}
