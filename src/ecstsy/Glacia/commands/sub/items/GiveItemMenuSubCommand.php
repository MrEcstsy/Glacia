<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\items;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\utils\lang\MessageTemplates;
use ecstsy\Glacia\utils\screens\items\GiveItemMenuScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GiveItemMenuSubCommand extends BaseSubCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize(MessageTemplates::render("general.ingame-only")));
            return;
        }

        GiveItemMenuScreen::displayCategoryMenu($sender);
    }

    public function getPermission(): string
    {
        return "glacia.give-item";
    }
}

