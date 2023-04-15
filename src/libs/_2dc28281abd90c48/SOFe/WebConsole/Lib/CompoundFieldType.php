<?php

declare(strict_types=1);

namespace libs\_2dc28281abd90c48\SOFe\WebConsole\Lib;

use Closure;
use SOFe\WebConsole\Api\FieldType;
use SOFe\WebConsole\Api\ObjectDef;

use function array_map;





























































































































































































































/**
 * @template T
 * @implements FieldType<T>
 */
final class CompoundFieldType implements FieldType {
    /**
     * @param CompoundSubfield<T, mixed>[] $subfields
     */
    public function __construct(
        public array $subfields,
    ) {
    }

    public function serializeType() : array {
        $subfields = [];
        foreach ($this->subfields as $subfield) {
            $subfields[] = $subfield->serializeType();
        }

        return [
            "type" => "compound",
            "fields" => $subfields,
        ];
    }

    public function serializeValue($value) : mixed {
        $output = [];
        foreach ($this->subfields as $field) {
            $output[$field->key] = $field->valueType->serializeValue($value);
            ;
        }
        return $output;
    }
}