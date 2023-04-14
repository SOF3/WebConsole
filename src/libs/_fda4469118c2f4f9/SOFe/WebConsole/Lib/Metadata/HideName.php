<?php

declare(strict_types=1);

namespace libs\_fda4469118c2f4f9\SOFe\WebConsole\Lib\Metadata;

use SOFe\WebConsole\Api\ObjectMetadata;

final class HideName implements ObjectMetadata {
    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/hide-name"] = true;
    }
}