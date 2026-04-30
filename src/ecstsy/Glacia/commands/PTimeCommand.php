<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PTimeCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("mode", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null) {
            return;
        }

        $mode = strtolower(trim((string) ($args["mode"] ?? "status")));
        if ($mode === "status") {
            $current = (string) ($session->getSetting("ptime_mode") ?? "sync");
            $locked = (bool) ($session->getSetting("ptime_lock") ?? false);
            $sender->sendMessage(C::colorize("&r&bPTime &8» &7Mode: &f{$current}&7, Lock: " . ($locked ? "&aON" : "&cOFF")));
            return;
        }

        if ($mode === "lock" || $mode === "unlock") {
            $session->setSetting("ptime_lock", $mode === "lock");
            GlaciaUtils::applyPersonalTimePreference($sender);
            $sender->sendMessage(C::colorize("&r&bPTime &8» &7Lock is now " . ($mode === "lock" ? "&aON" : "&cOFF") . "&7."));
            return;
        }

        if (!GlaciaUtils::isValidPersonalTimeMode($mode)) {
            $sender->sendMessage(C::colorize("&r&cUsage: /ptime <day|night|sunrise|sunset|noon|midnight|sync|lock|unlock|status>"));
            return;
        }

        $session->setSetting("ptime_mode", $mode);
        if ($mode !== "sync") {
            // Make /ptime <mode> work immediately and persist by default.
            $session->setSetting("ptime_lock", true);
        }
        GlaciaUtils::applyPersonalTimePreference($sender);
        $lockState = (bool) ($session->getSetting("ptime_lock") ?? false);
        $sender->sendMessage(C::colorize("&r&bPTime &8» &7Set personal time mode to &f{$mode}&7. Lock: " . ($lockState ? "&aON" : "&cOFF") . "&7."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
