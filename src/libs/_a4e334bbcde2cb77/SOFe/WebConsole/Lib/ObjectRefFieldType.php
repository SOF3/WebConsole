<?php

declare(strict_types=1);

namespace libs\_a4e334bbcde2cb77\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;













































































































































/**
 * @template I
 * @implements FieldType<I>
 */
final class ObjectRefFieldType implements FieldType {
    /**
     * @param ObjectDef<I> $theirKind
     */
    public function __construct(
        public ObjectDef $theirKind,
    ) {
    }

    public function serializeType() : array {
        return [
            "type" => "object",
            "group" => $this->theirKind->group,
            "kind" => $this->theirKind->kind,
        ];
    }

    public function serializeValue($value) : mixed {
        return $this->theirKind->desc->name($value);
    }
}