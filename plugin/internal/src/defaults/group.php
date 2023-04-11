<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use SOFe\WebConsole\Api\GroupDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Internal\Main;

final class Group {
    public const ID = "main";

    public static function register(Main $plugin, Registry $registry) : void {
        $registry->registerGroup(new GroupDef(
            id: self::ID,
            displayName: "main-group",
            displayPriority: 0,
        ));

        Players::register($plugin, $registry);
        Worlds::register($plugin, $registry);
        Logging::register($plugin, $registry);
    }
}
