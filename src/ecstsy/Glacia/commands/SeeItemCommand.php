<?php
declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\managers\ChatItemTracker;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\screens\ItemViewScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class SeeItemCommand extends BaseCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("name", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) return;

        $targetName = $args['name'] ?? "";
        if ($targetName === "") {
            $sender->sendMessage("Usage: /seeitem <player>");
            return;
        }

        $target = PlayerUtils::getPlayerByPrefix($targetName);

        if ($target === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => $targetName], "error"));
            return;
        }

        $item = ChatItemTracker::getLastItem($target);
        if ($item === null) {
            $sender->sendMessage("§cNo recent item found for player {$target->getName()}.");
            return;
        }

        $session = Loader::getPlayerManager()->getSession($target);

        ItemViewScreen::display($sender, $item);
        return;
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
