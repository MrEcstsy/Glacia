<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FixCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("modeOrPlayer", true));
        $this->registerArgument(1, new RawStringArgument("player", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        $modeOrPlayer = strtolower(trim((string) ($args["modeOrPlayer"] ?? "")));
        $mode = "hand";
        $targetName = (string) ($args["player"] ?? "");

        if ($modeOrPlayer === "all" || $modeOrPlayer === "hand") {
            $mode = $modeOrPlayer;
        } elseif ($modeOrPlayer !== "") {
            $targetName = $modeOrPlayer;
        }

        $target = $targetName !== "" ? PlayerUtils::getPlayerByPrefix($targetName) : $sender;
        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cThat player is not online."));
            return;
        }

        if ($target !== $sender && !$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cOnly admins can fix other players' items."));
            return;
        }

        if ($mode === "all" && !$sender->hasPermission("glacia.fixall")) {
            $sender->sendMessage(C::colorize("&r&cYou don't have permission to use /fix all."));
            return;
        }

        if ($mode === "all") {
            $fixed = GlaciaUtils::repairAll($target);
            if ($fixed <= 0) {
                $sender->sendMessage(GlaciaUtils::format("fixall.no_damaged", [], "error"));
                return;
            }

            $sender->sendMessage(C::colorize("&r&aRepaired &f{$fixed} &aitems for &f" . $target->getName() . "&a."));
            if ($target !== $sender) {
                $target->sendMessage(C::colorize("&r&aYour inventory and armor were repaired by &f" . $sender->getName() . "&a."));
            }
            return;
        }

        $fixed = GlaciaUtils::repairItemInHand($target);
        if (!$fixed) {
            $sender->sendMessage(GlaciaUtils::format("fix.no_damaged", [], "error"));
            return;
        }

        if ($target === $sender) {
            $sender->sendMessage(GlaciaUtils::format("fix.success", [], "success"));
            return;
        }

        $sender->sendMessage(C::colorize("&r&aRepaired the held item for &f" . $target->getName() . "&a."));
        $target->sendMessage(C::colorize("&r&aYour held item was repaired by &f" . $sender->getName() . "&a."));
    }

    public function getPermission(): string {
        return "glacia.fix";
    }
}