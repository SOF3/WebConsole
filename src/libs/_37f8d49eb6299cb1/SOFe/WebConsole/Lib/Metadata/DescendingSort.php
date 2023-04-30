<?php

declare(strict_types=1);

namespace libs\_37f8d49eb6299cb1\SOFe\WebConsole\Lib\Metadata;

use SOFe\WebConsole\Api\ObjectMetadata;












/**
 * On WebConsole web app, sort the name by descending order by default.
 */
final class DescendingSort implements ObjectMetadata {
    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/desc-name"] = true;
    }
}