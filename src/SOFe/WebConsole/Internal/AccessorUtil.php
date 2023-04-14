<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Internal;

use Closure;
use Exception;
use Generator;
use RuntimeException;
use libs\_fda4469118c2f4f9\SOFe\AwaitGenerator\Await;
use libs\_fda4469118c2f4f9\SOFe\AwaitGenerator\Channel;
use libs\_fda4469118c2f4f9\SOFe\AwaitGenerator\Loading;
use libs\_fda4469118c2f4f9\SOFe\AwaitGenerator\Traverser;
use SOFe\WebConsole\Api\AddObjectEvent;
use SOFe\WebConsole\Api\FieldDef;
use SOFe\WebConsole\Api\ObjectDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Api\RemoveObjectEvent;

use function array_keys;
use function assert;
use function count;
use function explode;
use function in_array;
use function json_encode;
use function json_last_error_msg;
use function ltrim;
use function str_ends_with;
use function strlen;
use function substr;
































































































































































































































































































































































































final class AccessorUtil {
    /**
     * @template I
     * @param ObjectDef<I> $objectDef
     * @param I $identity
     * @return Generator<mixed, mixed, mixed, array<string, mixed>>
     */
    public static function populateObjectFields(ObjectDef $objectDef, $identity, Closure $fieldFilter) : Generator {
        $item = [
            "_name" => $objectDef->desc->name($identity),
        ];

        $futures = [];
        foreach ($objectDef->fields as $field) {
            if ($fieldFilter($field->path)) {
                $futures[] = (function() use ($field, &$item, $identity) {
                    $fieldParts = explode(".", $field->path);
                    $rawValue = yield from $field->desc->get($identity);
                    $fieldValue = $field->type->serializeValue($rawValue);

                    /** @var array<string, mixed> $ptr */
                    $ptr = &$item;
                    foreach ($fieldParts as $fieldPart) {
                        if (!isset($ptr[$fieldPart])) {
                            $ptr[$fieldPart] = [];
                        }
                        $ptr = &$ptr[$fieldPart];
                    }
                    $ptr = $fieldValue;
                    unset($ptr);
                })();
            }
        }

        yield from Await::all($futures);

        return $item;
    }
}