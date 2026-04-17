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

final class CreateWarpCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("warp", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(GlaciaUtils::format("general.ingame-only"));
            return;
        }

        $warpName = isset($args["warp"]) ? $args["warp"] : null;

        if ($warpName === null) {
            $sender->sendMessage(GlaciaUtils::format("warp.usage"));
            return;
        }
    
        $warpManager = Loader::getWarpManager();
    
        if ($warpManager->getWarp($warpName) === null) {
            $warpManager->createWarp($sender, $warpName);
            $sender->sendMessage(GlaciaUtils::format("warp.created", ["warp" => $warpName], "success"));
        } else {
            $sender->sendMessage(GlaciaUtils::format("warp.exists", ["warp" => $warpName], "error"));
        }
    }

    public function getPermission(): string {
        return "glacia.create-warp";
    }
}