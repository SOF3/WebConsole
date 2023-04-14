<?php

declare(strict_types=1);

namespace libs\_67fd2f9d3627d2f8\SOFe\WebConsole\Lib\Metadata;

use SOFe\WebConsole\Api\ObjectMetadata;

final class HideName implements ObjectMetadata {
    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/hide-name"] = true;
    }
}