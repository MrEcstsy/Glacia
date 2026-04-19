<?php
declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use ecstsy\MartianUtilities\utils\PlayerUtils;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\managers\BragTracker;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\screens\BragScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SeeBragCommand extends BaseCommand {

    protected function prepare(): void {
        $this->setPermission("glacia.default");
        $this->registerArgument(0, new RawStringArgument("name", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(GlaciaUtils::format("general.ingame-only", [], "error"));
            return;
        }

        $targetName = $args["name"] ?? null;
        if ($targetName === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => "that player"], "error"));
            return;
        }

        $target = PlayerUtils::getPlayerByPrefix($targetName);
        if ($target === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => $targetName], "error"));
            return;
        }

        $targetName = $target->getName();
        if (!BragTracker::hasLastInventoryBrag($targetName)) {
            $sender->sendMessage(C::colorize("&r&cThat player has not bragged recently."));
            return;
        }

        BragScreen::display($target);
    }
}
