<?php

declare(strict_types=1);

namespace libs\_f06c6738f51e13fc\SOFe\WebConsole\Lib\Metadata;

use SOFe\WebConsole\Api\ObjectMetadata;












/**
 * On WebConsole web app, sort the name by descending order by default.
 */
final class DescendingSort implements ObjectMetadata {
    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/desc-name"] = true;
    }
}