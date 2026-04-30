<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\levels\PlayerLevelManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class LevelCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("arg1", true));
        $this->registerArgument(1, new RawStringArgument("arg2", true));
        $this->registerArgument(2, new RawStringArgument("amount", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $arg1 = strtolower((string) ($args["arg1"] ?? ""));
        if ($arg1 === "set" || $arg1 === "addxp") {
            $this->handleAdminAction($sender, $arg1, $args);
            return;
        }

        $target = $sender;
        if (($args["arg1"] ?? null) !== null) {
            $lookup = (string) $args["arg1"];
            $target = PlayerUtils::getPlayerByPrefix($lookup) ?? $sender;
            if ($target === $sender && strcasecmp($sender->getName(), $lookup) !== 0) {
                $session = Loader::getPlayerManager()->getSessionByPrefix($lookup);
                if ($session === null) {
                    $sender->sendMessage(C::colorize("&r&cThat player could not be found."));
                    return;
                }

                $this->sendLevelView($sender, $session->getUsername(), $session->getLevel(), $session->getLevelXp());
                return;
            }
        }

        $session = Loader::getPlayerManager()->getSession($target);
        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cThat player could not be found."));
            return;
        }

        $this->sendLevelView($sender, $target->getName(), $session->getLevel(), $session->getLevelXp());
    }

    private function handleAdminAction(Player $sender, string $mode, array $args) : void
    {
        if (!$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to manage levels."));
            return;
        }

        $lookup = (string) ($args["arg2"] ?? "");
        $value = GlaciaUtils::parseShorthandNumber((string) ($args["amount"] ?? ""));
        if ($lookup === "" || $value <= 0) {
            $sender->sendMessage(C::colorize("&r&cUsage: /level {$mode} <player> <amount>"));
            return;
        }

        $player = PlayerUtils::getPlayerByPrefix($lookup);
        $session = $player !== null ? Loader::getPlayerManager()->getSession($player) : Loader::getPlayerManager()->getSessionByPrefix($lookup);
        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cThat player could not be found."));
            return;
        }

        if ($mode === "set") {
            $level = min(PlayerLevelManager::getMaxLevel(), max(1, $value));
            $session->setLevelData($level, 0);
            $sender->sendMessage(C::colorize("&r&a✔ &r&7Set &f" . $session->getUsername() . "&7 to level &f" . number_format($level) . "&7."));
            return;
        }

        $playerObj = $session->getPlayer();
        if ($playerObj === null) {
            $sender->sendMessage(C::colorize("&r&cThat player must be online for addxp."));
            return;
        }

        PlayerLevelManager::addXp($playerObj, $value, "admin");
        $sender->sendMessage(C::colorize("&r&a✔ &r&7Added &f" . number_format($value) . " &7level XP to &f" . $session->getUsername() . "&7."));
    }

    private function sendLevelView(Player $viewer, string $name, int $level, int $xp) : void
    {
        $maxLevel = PlayerLevelManager::getMaxLevel();
        $viewer->sendMessage(C::colorize("&r&bLevel &8| &r&f{$name}"));
        $viewer->sendMessage(C::colorize("&r&7Level: &f" . number_format($level) . "&7/&f" . number_format($maxLevel)));

        if ($level >= $maxLevel) {
            $viewer->sendMessage(C::colorize("&r&7Progress: &bMAX LEVEL"));
            return;
        }

        $needed = PlayerLevelManager::getRequiredXpForLevel($level);
        $percent = min(100, (int) round(($xp / max(1, $needed)) * 100));
        $viewer->sendMessage(C::colorize("&r&7Progress: &f" . number_format($xp) . "&7/&f" . number_format($needed) . " XP &8({$percent}%&8)"));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
