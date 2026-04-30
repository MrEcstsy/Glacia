<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\world\audit\WorldAuditLogger;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class WorldCommand extends BaseCommand {
    /** @var array<string, array<string, array{token:string,expires:int}>> */
    private static array $deleteConfirmTokens = [];

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("arg1", true));
        $this->registerArgument(2, new RawStringArgument("arg2", true));
        $this->registerArgument(3, new RawStringArgument("arg3", true));
        $this->registerArgument(4, new RawStringArgument("arg4", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $service = Loader::getWorldManagerService();
        $action = strtolower((string) ($args["action"] ?? "help"));

        switch ($action) {
            case "list":
                if (!$this->requireActionPermission($sender, "glacia.command.world.list", "glacia.world.admin")) {
                    return;
                }
                $rows = $service->listWorldInfo();
                $sender->sendMessage(C::colorize("&r&8-=-=-=&7-=-=-= &bWorlds &7-=-=-=&8-=-=-="));
                foreach ($rows as $row) {
                    $sender->sendMessage(C::colorize(
                        "&r&f" . (string) $row["name"] .
                        " &8[&7gen:&f" . (string) $row["generator"] .
                        "&8] [&7loaded:&" . ((bool) $row["loaded"] ? "ayes" : "cno") .
                        "&8] [&7generated:&" . ((bool) $row["generated"] ? "ayes" : "cno") . "&8]"
                    ));
                }
                return;

            case "info":
                if (!$this->requireActionPermission($sender, "glacia.command.world.info", "glacia.world.admin")) {
                    return;
                }
                $world = (string) ($args["arg1"] ?? "");
                if ($world === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /world info <name>"));
                    return;
                }
                $info = $service->getWorldInfo($world);
                $sender->sendMessage(C::colorize("&r&bWorld: &f{$world}"));
                $sender->sendMessage(C::colorize("&r&7Loaded: &f" . (((bool) ($info["loaded"] ?? false)) ? "yes" : "no")));
                $sender->sendMessage(C::colorize("&r&7Generated: &f" . (((bool) ($info["generated"] ?? false)) ? "yes" : "no")));
                $sender->sendMessage(C::colorize("&r&7Players: &f" . (string) ($info["players"] ?? 0)));
                $sender->sendMessage(C::colorize("&r&7Generator: &f" . (string) ($info["generator"] ?? "unknown")));
                $gamerules = (array) ($info["gamerules"] ?? []);
                if ($gamerules !== []) {
                    $sender->sendMessage(C::colorize("&r&7Gamerules:"));
                    foreach ($gamerules as $rule => $ruleValue) {
                        $sender->sendMessage(C::colorize("&r&7- &f{$rule}&7 = &b" . $this->formatGameruleValue($ruleValue)));
                    }
                }
                return;

            case "create":
                if (!$this->requireActionPermission($sender, "glacia.command.world.create", "glacia.world.create")) {
                    return;
                }
                $name = (string) ($args["arg1"] ?? "");
                $generator = (string) ($args["arg2"] ?? "");
                $seed = (string) ($args["arg3"] ?? "");
                $preset = (string) ($args["arg4"] ?? "");
                if ($name === "" || $generator === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /world create <name> <generator> [seed] [preset]"));
                    $sender->sendMessage(C::colorize("&r&7Generators: &fnormal&7, &fflat&7, &fvanilla_overworld&7, &fvanilla_nether&7, &fend"));
                    return;
                }
                $ok = $service->generateWorld($name, $generator, $seed !== "" ? $seed : null, $preset) && $service->loadWorld($name);
                if (!$ok) {
                    $sender->sendMessage(C::colorize("&r&cFailed to create world."));
                    return;
                }
                WorldAuditLogger::log("CreateByCommand", [
                    "actor" => $sender->getName(),
                    "world" => $name,
                    "generator" => $generator,
                    "result" => "ok",
                ]);
                $seedLabel = $seed !== "" ? $seed : "random";
                $sender->sendMessage(C::colorize("&r&aCreated and loaded world &f{$name}&a with &f{$generator}&a (seed: &f{$seedLabel}&a)."));
                return;

            case "load":
                if (!$this->requireActionPermission($sender, "glacia.command.world.load", "glacia.world.load")) {
                    return;
                }
                $name = (string) ($args["arg1"] ?? "");
                if ($name === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /world load <name>"));
                    return;
                }
                $ok = $service->loadWorld($name);
                $sender->sendMessage(C::colorize($ok ? "&r&aLoaded world &f{$name}&a." : "&r&cFailed to load world."));
                return;

            case "unload":
                if (!$this->requireActionPermission($sender, "glacia.command.world.unload", "glacia.world.unload")) {
                    return;
                }
                $name = (string) ($args["arg1"] ?? "");
                if ($name === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /world unload <name>"));
                    return;
                }
                $ok = $service->unloadWorld($name, $sender->getName());
                $sender->sendMessage(C::colorize($ok ? "&r&aUnloaded world &f{$name}&a." : "&r&cCould not unload world (default, has players, missing, or failed)."));
                return;

            case "delete":
                if (!$this->requireActionPermission($sender, "glacia.command.world.delete", "glacia.world.delete")) {
                    return;
                }
                $name = (string) ($args["arg1"] ?? "");
                $confirm = strtolower((string) ($args["arg2"] ?? ""));
                if ($name === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /world delete <name> [confirm]"));
                    return;
                }
                if ($confirm !== "confirm" && !$this->isDeleteTokenValid($sender, $name, $confirm)) {
                    $token = $this->issueDeleteToken($sender, $name);
                    $sender->sendMessage(C::colorize("&r&cThis is destructive. Re-run with confirmation:"));
                    $sender->sendMessage(C::colorize("&r&7/world delete &f{$name} &f{$token}"));
                    return;
                }
                $ok = $service->deleteWorld($name, $sender->getName());
                $sender->sendMessage(C::colorize($ok ? "&r&aDeleted world &f{$name}&a." : "&r&cCould not delete world (default, loaded with players, missing, or filesystem failure)."));
                return;

            case "tp":
                if (!$this->requireActionPermission($sender, "glacia.command.world.teleport", "glacia.world.teleport")) {
                    return;
                }
                if (!$sender instanceof Player) {
                    $sender->sendMessage(C::colorize("&r&cRun this in-game or provide a player as sender is console."));
                    return;
                }
                $name = (string) ($args["arg1"] ?? "");
                $targetName = (string) ($args["arg2"] ?? "");
                if ($name === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /world tp <name> [player]"));
                    return;
                }
                $target = $targetName !== "" ? Loader::getInstance()->getServer()->getPlayerExact($targetName) : null;
                if ($targetName !== "" && $target === null) {
                    $sender->sendMessage(C::colorize("&r&cTarget player not found."));
                    return;
                }
                $ok = $service->teleportToWorld($sender, $name, $target);
                $sender->sendMessage(C::colorize($ok ? "&r&aTeleported." : "&r&cFailed to teleport to that world."));
                return;

            case "setspawn":
                if (!$this->requireActionPermission($sender, "glacia.command.world.setspawn", "glacia.world.setspawn")) {
                    return;
                }
                if (!$sender instanceof Player) {
                    $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
                    return;
                }
                $name = (string) ($args["arg1"] ?? "");
                if ($name === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /world setspawn <name>"));
                    return;
                }
                $ok = $service->setWorldSpawn($name, $sender->getPosition(), $sender->getName());
                $sender->sendMessage(C::colorize($ok ? "&r&aSpawn set for &f{$name}&a." : "&r&cFailed to set spawn (world not loaded)."));
                return;

            case "gamerule":
                if (!$this->requireActionPermission($sender, "glacia.command.world.gamerule", "glacia.world.admin")) {
                    return;
                }
                $worldName = (string) ($args["arg1"] ?? "");
                $rule = strtolower((string) ($args["arg2"] ?? ""));
                $value = (string) ($args["arg3"] ?? "");
                $supported = $service->getSupportedGamerules();

                if ($worldName === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /world gamerule <world> [rule] [value]"));
                    $sender->sendMessage(C::colorize("&r&7Supported: &f" . implode("&7, &f", array_keys($supported))));
                    return;
                }

                if ($rule === "") {
                    $sender->sendMessage(C::colorize("&r&bGamerules for &f{$worldName}&b:"));
                    foreach (array_keys($supported) as $supportedRule) {
                        $state = $service->getWorldGamerule($worldName, $supportedRule);
                        if ($state === null) {
                            continue;
                        }
                        $sender->sendMessage(C::colorize(
                            "&r&7- &f{$supportedRule}&7 = &b" . $this->formatGameruleValue($state["value"]) .
                            "&7 (" . ((bool) $state["loaded"] ? "live" : "saved") . ")"
                        ));
                    }
                    return;
                }

                if (!isset($supported[$rule])) {
                    $sender->sendMessage(C::colorize("&r&cUnsupported gamerule. Supported: &f" . implode("&7, &f", array_keys($supported))));
                    return;
                }

                if ($value === "") {
                    $state = $service->getWorldGamerule($worldName, $rule);
                    if ($state === null) {
                        $sender->sendMessage(C::colorize("&r&cUnable to read that gamerule."));
                        return;
                    }
                    $sender->sendMessage(C::colorize(
                        "&r&b{$rule} &7for &f{$worldName}&7: &b" . $this->formatGameruleValue($state["value"]) .
                        "&7 (" . ((bool) $state["loaded"] ? "live" : "saved for next load") . ")"
                    ));
                    return;
                }

                $result = $service->setWorldGamerule($worldName, $rule, $value, $sender->getName());
                if ($result === null) {
                    $typeLabel = $supported[$rule] === "bool" ? "true/false" : "0-4096";
                    $sender->sendMessage(C::colorize("&r&cInvalid value for &f{$rule}&c. Expected &f{$typeLabel}&c."));
                    return;
                }

                $sender->sendMessage(C::colorize(
                    "&r&aSet &f{$result["rule"]}&a for &f{$worldName}&a to &f" . $this->formatGameruleValue($result["value"]) .
                    "&a." . ((bool) $result["loaded"] ? "" : " &7It will apply when the world is loaded.")
                ));
                return;
        }

        $sender->sendMessage(C::colorize("&r&eWorld Manager"));
        $sender->sendMessage(C::colorize("&r&7/world list"));
        $sender->sendMessage(C::colorize("&r&7/world info <name>"));
        $sender->sendMessage(C::colorize("&r&7/world create <name> <generator> [seed] [preset]"));
        $sender->sendMessage(C::colorize("&r&7Blank seed = random world seed"));
        $sender->sendMessage(C::colorize("&r&7/world load <name>"));
        $sender->sendMessage(C::colorize("&r&7/world unload <name>"));
        $sender->sendMessage(C::colorize("&r&7/world delete <name>"));
        $sender->sendMessage(C::colorize("&r&7/world tp <name> [player]"));
        $sender->sendMessage(C::colorize("&r&7/world setspawn <name>"));
        $sender->sendMessage(C::colorize("&r&7/world gamerule <world> [rule] [value]"));
    }

    private function requireActionPermission(CommandSender $sender, string $permission, ?string $legacyPermission = null): bool
    {
        if (
            $sender->hasPermission("glacia.admin") ||
            $sender->hasPermission("glacia.command.world.admin") ||
            $sender->hasPermission("glacia.world.admin") ||
            $sender->hasPermission($permission) ||
            ($legacyPermission !== null && $sender->hasPermission($legacyPermission))
        ) {
            return true;
        }
        $sender->sendMessage(C::colorize("&r&cYou do not have permission (&f{$permission}&c)."));
        return false;
    }

    private function issueDeleteToken(CommandSender $sender, string $world): string
    {
        $name = strtolower(trim($world));
        $senderKey = strtolower($sender->getName());
        $token = substr(hash("sha1", $senderKey . ":" . $name . ":" . microtime(true)), 0, 6);
        self::$deleteConfirmTokens[$senderKey][$name] = [
            "token" => $token,
            "expires" => time() + 30,
        ];
        return $token;
    }

    private function isDeleteTokenValid(CommandSender $sender, string $world, string $token): bool
    {
        $name = strtolower(trim($world));
        $senderKey = strtolower($sender->getName());
        $entry = self::$deleteConfirmTokens[$senderKey][$name] ?? null;
        if (!is_array($entry)) {
            return false;
        }
        if (($entry["expires"] ?? 0) < time()) {
            return false;
        }
        return isset($entry["token"]) && hash_equals((string) $entry["token"], $token);
    }

    public function getPermission(): string
    {
        return "glacia.command.world.admin";
    }

    private function formatGameruleValue(bool|int|string|float $value): string
    {
        if (is_bool($value)) {
            return $value ? "true" : "false";
        }
        return (string) $value;
    }
}
