<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class RemoveWarpCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("warp", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $warpName = isset($args["warp"]) ? $args["warp"] : null;

        if ($warpName === null || trim($warpName) === "") {
            $sender->sendMessage(C::RED . "Usage: /removewarp <warp>");
            return;
        }

        $warp = Loader::getWarpManager()->getWarp($warpName);
        if ($warp !== null) {
            Loader::getWarpManager()->deleteWarp($warp);
            $sender->sendMessage(C::DARK_RED . "Warp '{$warpName}' has been deleted.");
            return;
        }
        $sender->sendMessage(C::RED . "Warp '{$warpName}' does not exist.");
    }

    public function getPermission(): string {
        return "glacia.remove-warp";
    }
}