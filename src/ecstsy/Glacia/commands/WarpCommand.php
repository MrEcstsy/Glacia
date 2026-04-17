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
            $sender->sendMessage(C::RED . "This command can only be used in-game.");
            return;
        }

        $warpName = isset($args["warp"]) ? $args["warp"] : null;
        $player = isset($args["name"]) ? PlayerUtils::getPlayerByPrefix($args["name"]) : $sender;

        $warp = Loader::getWarpManager()->getWarp($warpName);

        if ($warp === null) {
            $sender->sendMessage(C::RED . "Warp '{$warpName}' does not exist.");
            return;
        }
    
        if ($player === null && isset($args["name"])) {
            $sender->sendMessage(C::RED . "Player '{$args["name"]}' not found.");
            return;
        }
        
        if ($player !== null) {
            if (!$sender->hasPermission("glacia.warp.others")) {
                $sender->sendMessage(C::RED . "You do not have permission to warp others.");
                return;
            }
        
            $warp->teleport($player);
            $sender->sendMessage(C::GREEN . "Player '{$player->getName()}' has been warped to '{$warpName}'.");
        } else {
            $warp->teleport($sender);
            $sender->sendMessage(C::GREEN . "You have been warped to '{$warpName}'.");
        }        
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}