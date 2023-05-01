<?php

declare(strict_types=1);

namespace SOFe\WebConsole\Defaults;

use SOFe\WebConsole\Api\GroupDef;
use SOFe\WebConsole\Api\Registry;
use SOFe\WebConsole\Internal\Main;
use SOFe\WebConsole\Lib\MainGroup;

final class Group {
    public const ID = MainGroup::GROUP_ID;

    public static function register(Main $plugin, Registry $registry) : void {
        $registry->registerGroup(new GroupDef(
            id: self::ID,
            displayName: "main-group",
            displayPriority: 0,
        ));

        Logging::registerKind($plugin, $registry);
        Players::registerKind($plugin, $registry);
        Worlds::registerKind($plugin, $registry);

        Logging::registerFields($registry);
        Players::registerFields($plugin, $registry);
        Worlds::registerFields($plugin, $registry);
    }
}
