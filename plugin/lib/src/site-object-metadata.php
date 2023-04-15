<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Lib\Metadata;

use SOFe\WebConsole\Api\ObjectMetadata;

final class HideName implements ObjectMetadata {
    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/hide-name"] = true;
    }
}

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
