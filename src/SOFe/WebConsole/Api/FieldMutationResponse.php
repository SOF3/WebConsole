<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Closure;
use Generator;
use libs\_f06c6738f51e13fc\SOFe\AwaitGenerator\Traverser;
use function sprintf;


































































































final class FieldMutationResponse {
    /**
     * @param FieldResponseMetadata[] $metadata
     */
    public function __construct(
        public bool $success,
        public ?string $errorCode,
        public ?string $i18nMessage,
        public array $metadata = [],
    ) {
    }

    public static function success() : self {
        return new self(true, null, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize() : array {
        return [
            "success" => $this->success,
            "errorCode" => $this->errorCode,
            "message" => $this->i18nMessage,
            "metadata" => $this->getMetadata(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata() : array {
        $metadata = [];
        foreach ($this->metadata as $datum) {
            $datum->apply($metadata);
        }
        return $metadata;
    }
}