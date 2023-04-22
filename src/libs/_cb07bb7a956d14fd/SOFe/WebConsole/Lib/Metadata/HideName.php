<?php

declare(strict_types=1);

namespace libs\_cb07bb7a956d14fd\SOFe\WebConsole\Lib\Metadata;

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