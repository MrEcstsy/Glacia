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

final class RemoveWarpCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("warp", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(GlaciaUtils::format("general.ingame-only", [], "error"));
            return;
        }

        $warpName = isset($args["warp"]) ? $args["warp"] : null;

        if ($warpName !== null) {
            $warp = Loader::getWarpManager()->getWarp($warpName);
            if ($warp !== null) { // Probably redundant 
                Loader::getWarpManager()->deleteWarp($warp);
                $sender->sendMessage(C::DARK_RED . "Warp '{$warpName}' has been deleted.");
            } else {
                $sender->sendMessage(C::RED . "Warp '{$warpName}' does not exist.");
            }
        } else {
            $sender->sendMessage(C::RED . "Warp '{$warpName}' does not exist.");
        }
    }

    public function getPermission(): string {
        return "glacia.remove-warp";
    }
}