<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class WarpCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("warp", false));
        $this->registerArgument(1, new RawStringArgument("name", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&cThis command can only be used in-game."));
            return;
        }

        $warpName = isset($args["warp"]) ? $args["warp"] : null;
        $player = isset($args["name"]) ? PlayerUtils::getPlayerByPrefix($args["name"]) : $sender;

        $warp = Loader::getWarpManager()->getWarp($warpName);

        if ($warp === null) {
            $sender->sendMessage(C::colorize("&cWarp '{$warpName}' does not exist."));
            return;
        }
    
        if ($player === null && isset($args["name"])) {
            $sender->sendMessage(C::colorize("&cPlayer '{$args["name"]}' not found."));
            return;
        }
        
        if ($player !== null) {
            if (!$sender->hasPermission("glacia.warp.others")) {
                $sender->sendMessage(C::colorize("&cYou do not have permission to warp others."));
                return;
            }
        
            if ($warp->teleport($player)) {
                $sender->sendMessage(C::colorize("&aPlayer '{$player->getName()}' has been warped to '{$warpName}'."));
            } else {
                $sender->sendMessage(C::colorize("&cWarp '{$warpName}' could not be loaded right now."));
            }
        } else {
            if ($warp->teleport($sender)) {
                $sender->sendMessage(C::colorize("&aYou have been warped to '{$warpName}'."));
            } else {
                $sender->sendMessage(C::colorize("&cWarp '{$warpName}' could not be loaded right now."));
            }
        }        
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
