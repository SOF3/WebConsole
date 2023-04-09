<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Api;

use RuntimeException;

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

    /**
     * @param ObjectDef<mixed> $def
     */
    public function registerObject(ObjectDef $def) : void {
        if (isset($this->objectKinds[$def->id()])) {
            throw new RuntimeException("Object kind {$def->id()} was already registered");
        }

        $this->objectKinds[$def->id()] = $def;
    }

    /**
     * @param FieldDef<mixed, mixed> $def
     */
    public function registerField(FieldDef $def) : void {
        if (!isset($this->objectKinds[$def->objectId()])) {
            throw new RuntimeException("Cannot register field for unknown object kind {$def->objectId()}");
        }

        $objectDef = $this->objectKinds[$def->objectId()];
        $objectDef->fields[$def->path] = $def;
    }

    public function provideFluent(string $comp, string $locale, string $fluent) : void {
        $this->fluentLocales[$locale] ??= new FluentLocale($locale);
        $this->fluentLocales[$locale]->provide($comp, $fluent);
    }
}
