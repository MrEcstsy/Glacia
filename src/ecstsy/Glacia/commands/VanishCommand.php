<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\staff\StaffModeManager;
use ecstsy\Glacia\server\staff\VanishManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class VanishCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $targetName = trim((string) ($args["player"] ?? ""));
        $target = null;

        if ($targetName !== "") {
            if (!$sender->hasPermission("glacia.vanish.other") && !$sender->hasPermission("glacia.admin")) {
                $sender->sendMessage(C::colorize("&cYou do not have permission to vanish other players."));
                return;
            }

            $target = PlayerUtils::getPlayerByPrefix($targetName);
            if (!$target instanceof Player) {
                $sender->sendMessage(C::colorize("&cThat player is not online."));
                return;
            }
        } elseif ($sender instanceof Player) {
            $target = $sender;
        }

        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&cUse this command in-game, or target an online player."));
            return;
        }

        if (StaffModeManager::isInStaffMode($target) && VanishManager::isVanished($target)) {
            $sender->sendMessage(C::colorize("&eThat player is in staff mode. Exit staff mode before revealing them."));
            return;
        }

        $vanished = VanishManager::toggle($target, true);
        if ($target === $sender) {
            $sender->sendMessage(C::colorize($vanished
                ? "&aYou are now vanished."
                : "&aYou are now visible."));
            return;
        }

        $sender->sendMessage(C::colorize($vanished
            ? "&aVanished &f" . $target->getName() . "&a."
            : "&aMade &f" . $target->getName() . "&a visible."));
        $target->sendMessage(C::colorize($vanished
            ? "&eYou have been vanished by &f" . $sender->getName() . "&e."
            : "&eYou have been revealed by &f" . $sender->getName() . "&e."));
    }

    public function getPermission(): string
    {
        return "glacia.vanish";
    }
}
