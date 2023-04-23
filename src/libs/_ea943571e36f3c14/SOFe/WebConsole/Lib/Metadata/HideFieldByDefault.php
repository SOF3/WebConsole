<?php

declare(strict_types=1);

namespace libs\_ea943571e36f3c14\SOFe\WebConsole\Lib\Metadata;

use SOFe\WebConsole\Api\FieldMetadata;












final class HideFieldByDefault implements FieldMetadata {
    public function apply(array &$metadata) : void {
        $metadata["webconsole/site/hide-by-default"] = true;
    }
}