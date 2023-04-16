<?php

declare(strict_types=1);

namespace libs\_85f6d346dd7f97fb\SOFe\WebConsole\Lib\Metadata;

use SOFe\WebConsole\Api\FieldMetadata;












final class HideFieldByDefault implements FieldMetadata {
    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/hide-by-default"] = true;
    }
}