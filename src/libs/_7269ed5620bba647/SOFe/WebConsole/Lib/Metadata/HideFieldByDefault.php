<?php

declare(strict_types=1);

namespace libs\_7269ed5620bba647\SOFe\WebConsole\Lib\Metadata;

use SOFe\WebConsole\Api\FieldMetadata;












final class HideFieldByDefault implements FieldMetadata {
    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/hide-by-default"] = true;
    }
}