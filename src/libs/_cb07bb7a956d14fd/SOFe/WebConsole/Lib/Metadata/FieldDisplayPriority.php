<?php

declare(strict_types=1);

namespace libs\_cb07bb7a956d14fd\SOFe\WebConsole\Lib\Metadata;

use SOFe\WebConsole\Api\FieldMetadata;

final class FieldDisplayPriority implements FieldMetadata {
    public function __construct(
        public int $displayPriority,
    ) {
    }

    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/display-priority"] = $this->displayPriority;
    }
}