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
        try {
            $this->init();
        } catch (DisablePluginException $e) {
            $this->getLogger()->critical($e->getMessage());
            throw $e;
        }
    }

    private function init() : void {
        $this->saveDefaultConfig();

        $registry = new Registry;

        $handler = new Handler($registry);
        $server = new HttpServer(
            logger: new PrefixedLogger($this->getLogger(), "HttpServer"),
            address: $this->getConfigString("api-server-address"),
            port: $this->getConfigInt("api-server-port"),
            timeout: $this->getConfigInt("client-timeout"),
            maxRequestSize: $this->getConfigInt("max-request-size"),
            handler: function(HttpRequest $request) use ($handler) {
                return yield from $handler->handle($request);
            },
        );

        $this->getScheduler()->scheduleTask(new ClosureTask(fn() => $server->listen()));
        $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(fn() => $server->tick()), 1, 1);

        Defaults\Group::register($this, $registry);
    }

    private function invalidConfig(string $err) : never {
        throw new DisablePluginException("Invalid config at {$this->getConfig()->getPath()}: $err");
    }

    private function getConfigString(string $key) : string {
        if (!$this->getConfig()->exists($key)) {
            $this->invalidConfig("missing key \"$key\"");
        }

        $value = $this->getConfig()->get($key);
        if (!is_string($value)) {
            $this->invalidConfig("key \"$key\" is not a string, try surrounding the value with \"quotes\"");
        }

        return $value;
    }

    private function getConfigInt(string $key) : int {
        if (!$this->getConfig()->exists($key)) {
            $this->invalidConfig("missing key \"$key\"");
        }

        $value = $this->getConfig()->get($key);
        if (!is_int($value)) {
            $this->invalidConfig("key \"$key\" is not an integer");
        }

        return $value;
    }
}
