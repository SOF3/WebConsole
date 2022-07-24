<?php

declare(strict_types=1);

namespace SOFe\WebConsole;

use pocketmine\plugin\DisablePluginException;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use PrefixedLogger;

use function is_int;
use function is_string;

final class Main extends PluginBase {
    protected function onEnable() : void {
        $this->saveDefaultConfig();

        $handler = new Handler;
        $server = new HttpServer(
            logger: new PrefixedLogger($this->getLogger(), "HttpServer"),
            address: $this->getConfigString("api-server-address"),
            port: $this->getConfigInt("api-server-port"),
            timeout: $this->getConfigInt("client-timeout"),
            maxRequestSize: $this->getConfigInt("max-request-size"),
            handler: function(HttpRequest $request) use($handler) {
                return yield from $handler->handle($request);
            },
        );
        $server->listen();
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(fn() => $server->tick()), 1);
    }

    private function getConfigString(string $key) : string {
        if (!$this->getConfig()->exists($key)) {
            throw new DisablePluginException("Invalid config: missing key \"$key\"");
        }

        $value = $this->getConfig()->get($key);
        if (!is_string($value)) {
            throw new DisablePluginException("Invalid config: key \"$key\" is not a string, try surrounding the value with \"quotes\"");
        }

        return $value;
    }

    private function getConfigInt(string $key) : int {
        if (!$this->getConfig()->exists($key)) {
            throw new DisablePluginException("Invalid config: missing key \"$key\"");
        }

        $value = $this->getConfig()->get($key);
        if (!is_int($value)) {
            throw new DisablePluginException("Invalid config: key \"$key\" is not an integer");
        }

        return $value;
    }
}
