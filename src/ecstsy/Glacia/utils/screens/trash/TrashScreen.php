<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\trash;

use ecstsy\Glacia\utils\lang\MessageTemplates;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\inventory\Inventory;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class TrashScreen
{
    public static function display(Player $player): void
    {
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $menu->setName(C::colorize(MessageTemplates::render("trash.menu.title")));
        $menu->setInventoryCloseListener(function (Player $player, Inventory $inventory): void {
            $inventory->clearAll();
            $player->sendMessage(C::colorize(MessageTemplates::render("trash.message.closed")));
        });

        $menu->send($player);
        $player->sendMessage(C::colorize(MessageTemplates::render("trash.message.open")));
    }
}
