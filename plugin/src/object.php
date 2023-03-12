<?php

declare(strict_types=1);

namespace SOFe\WebConsole;

use Generator;
use RuntimeException;
use SOFe\AwaitGenerator\Traverser;
use function array_key_exists;
use function sprintf;

final class Registry {
    /** @var array<string, GroupDef> */
    public array $groups = [];

    /** @var array<string, ObjectDef<mixed>> */
    public array $objectKinds = [];

    /** @var array<string, FluentLocale> */
    public array $fluentLocales = [];

    public function registerGroup(GroupDef $def) : void {
        if (isset($this->objectKinds[$def->id])) {
            throw new RuntimeException("Object kind {$def->id} was already registered");
        }

        $this->groups[$def->id] = $def;
    }

    public function registerObject(ObjectDef $def) : void {
        if (isset($this->objectKinds[$def->id()])) {
            throw new RuntimeException("Object kind {$def->id()} was already registered");
        }

        $this->objectKinds[$def->id()] = $def;
    }

    public function registerField(FieldDef $def) : void {
        if (!isset($this->objectKinds[$def->objectId()])) {
            throw new RuntimeException("Cannot register field for unknown object kind {$def->objectId()}");
        }

        $objectDef = $this->objectKinds[$def->objectId()];
        $objectDef->fields[$def->path] = $def;
    }

    public function provideFluent(string $owner, string $locale, string $fluent) : void {
        $this->fluentLocales[$locale] ??= new FluentLocale($locale);
        $this->fluentLocales[$locale]->provide($owner, $fluent);
    }
}

final class GroupDef {
    public function __construct(
        public string $id,
        public string $displayName,
    ) {
    }
}

final class FluentLocale {
    /** @var array<string, true> */
    public array $comps = [];
    public string $fluent = "";

    public function __construct(
        public string $locale,
    ) {
    }

    public function provide(string $comp, string $fluent) {
        if (array_key_exists($comp, $this->comps)) {
            throw new RuntimeException("Fluent bundle for \"$comp\" was provided multiple times");
        }
        $this->comps[$comp] = true;
        $this->fluent .= $fluent . "\n";
    }
}

/**
 * @template I The "identity" type, which is the type that the object lister provides to field suppliers.
 */
final class ObjectDef {
    /** @var array<string, FieldDef<I, mixed>> */
    public array $fields = [];


    public function __construct(
        public string $group,
        public string $kind,
        public string $displayName,
        public ObjectDesc $desc,
    ) {
    }

    public function id() : string {
        return sprintf("%s/%s", $this->group, $this->kind);
    }
}

/**
 * @template I
 */
interface ObjectDesc {
    /**
     * Converts an object to its name.
     *
     * @param I $object
     */
    public function name($object) : string;

    /**
     * Lists all possible objects.
     *
     * The order of traversal does not need to be sorted,
     * but it *should* try to be stable for better user experience.
     *
     * The returned traverser may be interrupted with an InterruptException,
     * in which case any backing queries should be terminated.
     *
     * @return Traverser<I>
     */
    public function list() : Traverser;

    /**
     * Watches the addition and removal of objects.
     *
     * The traverser yields an ObjectEvent indicating the addition or removal of an event.
     *
     * The returned traverser may be interrupted with an InterruptException,
     * in which case any backing queries should be terminated.
     *
     * @param bool $withExisting If true, an AddObjectEvent is yielded for each existing item in `list()`
     *                           before yielding new changes.
     * @return Traverser<AddObjectEvent|RemoveObjectEvent>
     */
    public function watch(bool $withExisting) : Traverser;

    /**
     * Fetches an object given its name.
     *
     * @return Generator<mixed, mixed, mixed, ?I>
     */
    public function get(string $name) : Generator;
}

/**
 * @template I
 */
final class AddObjectEvent {
    /** @param I $item */
    public function __construct(public $item) {
    }
}

final class RemoveObjectEvent {
    public function __construct(public string $name) {
    }
}
