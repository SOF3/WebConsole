<?php

declare(strict_types=1);

namespace libs\_6c4285df04e833c0\SOFe\WebConsole\Lib\Metadata;

use SOFe\WebConsole\Api\FieldMetadata;












final class HideFieldByDefault implements FieldMetadata {
    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/hide-by-default"] = true;
    }
}