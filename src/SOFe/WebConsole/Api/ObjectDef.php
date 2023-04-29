<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use Exception;
use Generator;
use libs\_192b30f44185144c\SOFe\AwaitGenerator\Traverser;
use function sprintf;










/**
 * @template I The "identity" type, which is the type that the object lister provides to field suppliers.
 */
final class ObjectDef {
    /** @var array<string, FieldDef<I, mixed>> */
    public array $fields = [];


    /**
     * @param ObjectDesc<I> $desc
     * @param ObjectMetadata[] $metadata
     */
    public function __construct(
        public string $group,
        public string $kind,
        public string $displayName,
        public ObjectDesc $desc,
        public array $metadata,
    ) {
    }

    public static function idOf(string $group, string $kind) : string {
        return sprintf("%s/%s", $group, $kind);
    }

    public function id() : string {
        return self::idOf($this->group, $this->kind);
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