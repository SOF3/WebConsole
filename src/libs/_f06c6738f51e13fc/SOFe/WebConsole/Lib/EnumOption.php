<?php

declare(strict_types=1);

namespace libs\_f06c6738f51e13fc\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;



























































































































final class EnumOption {
    public function __construct(
        public string $id,
        public string $i18nKey,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeType() : array {
        return [
            "id" => $this->id,
            "i18n" => $this->i18nKey,
        ];
    }
}