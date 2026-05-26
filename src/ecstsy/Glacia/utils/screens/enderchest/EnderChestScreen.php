<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\enderchest;

use ecstsy\Glacia\utils\lang\MessageTemplates;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class EnderChestScreen
{
    public static function displayOwn(Player $player): void
    {
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST, $player->getEnderInventory());
        $menu->setName(C::colorize(MessageTemplates::render("enderchest.title.self")));
        $menu->send($player);
        $player->sendMessage(C::colorize(MessageTemplates::render("enderchest.opened.self")));
    }

    public static function displayEditable(Player $viewer, Player $target): void
    {
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST, $target->getEnderInventory());
        $menu->setName(C::colorize(MessageTemplates::render("enderchest.title.other", ["player" => $target->getName()])));
        $menu->send($viewer);
    }

    public static function displayReadonly(Player $viewer, Player $target): void
    {
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST, $target->getEnderInventory());
        $menu->setName(C::colorize(MessageTemplates::render("enderchest.title.other", ["player" => $target->getName()])));
        $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction): void {
        }));
        $menu->send($viewer);
    }
}
