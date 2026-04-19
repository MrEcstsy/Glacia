<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class EnvoyCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("value", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $manager = Loader::getEnvoyManager();
        $action = strtolower((string) ($args["action"] ?? ""));
        $value = (string) ($args["value"] ?? "");

        if ($action === "") {
            $active = $manager->getActive();
            if ($active === null) {
                $sender->sendMessage(C::colorize("&r&l&bEnvoy"));
                $sender->sendMessage(C::colorize("&r&7Next envoy in &f" . GeneralUtils::translateTime($manager->getTimeLeft()) . "&7."));
                return;
            }

            $sender->sendMessage(C::colorize("&r&l&bEnvoy Event"));
            $sender->sendMessage(C::colorize("&r&7Time Left: &f" . GeneralUtils::translateTime($manager->getTimeLeft())));
            $sender->sendMessage(C::colorize("&r&7Crates Remaining: &f" . count((array) ($active["positions"] ?? []))));
            return;
        }

        if (!$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to manage envoys."));
            return;
        }

        switch ($action) {
            case "start":
                $sender->sendMessage(C::colorize($manager->forceStart()
                    ? "&r&aStarted an envoy event."
                    : "&r&cCould not start envoy. End the active one first."
                ));
                return;

            case "stop":
            case "end":
                $sender->sendMessage(C::colorize($manager->forceStop()
                    ? "&r&aStopped the active envoy event."
                    : "&r&cThere is no active envoy."
                ));
                return;

            case "next":
                $seconds = GeneralUtils::parseTimeString($value);
                if ($seconds <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /envoy next <duration>"));
                    return;
                }
                $manager->setNextDelay($seconds);
                $sender->sendMessage(C::colorize("&r&aSet the next envoy to begin in &f" . GeneralUtils::translateTime($seconds) . "&a."));
                return;

            case "time":
                $seconds = GeneralUtils::parseTimeString($value);
                if ($seconds <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /envoy time <duration>"));
                    return;
                }
                $sender->sendMessage(C::colorize($manager->setActiveTime($seconds)
                    ? "&r&aSet the active envoy timer to &f" . GeneralUtils::translateTime($seconds) . "&a."
                    : "&r&cThere is no active envoy."
                ));
                return;

            case "types":
            case "list":
                $sender->sendMessage(C::colorize("&r&bEnvoy Types&7: &f" . implode("&7, &f", $manager->getTypeIds())));
                return;
        }

        $sender->sendMessage(C::colorize("&r&e/envoy &7View the next envoy."));
        $sender->sendMessage(C::colorize("&r&e/envoy start&7, &e/envoy stop"));
        $sender->sendMessage(C::colorize("&r&e/envoy next <duration>&7, &e/envoy time <duration>&7, &e/envoy types"));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
