<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use SOFe\WebConsole\GroupDef;
use SOFe\WebConsole\Main;
use SOFe\WebConsole\Registry;

final class Group {
    public const ID = "main";

    public static function register(Main $plugin, Registry $registry) : void {
        $registry->registerGroup(new GroupDef(
            id: self::ID,
            displayName: "main-group",
        ));
        $registry->provideFluent("main", "en", <<<EOF
            main-group = Main
            EOF);
        Players::register($plugin, $registry);
    }
}
