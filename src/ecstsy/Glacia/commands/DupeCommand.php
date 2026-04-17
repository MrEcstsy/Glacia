<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class DupeCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if(!$sender instanceof Player){
            return;
        }

        $item = $sender->getInventory()->getItemInHand();

        if($item->isNull() || $item->getTypeId() === VanillaItems::AIR()->getTypeId()){
            $sender->sendMessage(GlaciaUtils::format("dupe.no_item", [], "error"));
            return;
        }

        $duplicate = clone $item;
        $inventory = $sender->getInventory();

        if(!$inventory->canAddItem($duplicate)){
            $sender->sendMessage(GlaciaUtils::format("dupe.no_space", [], "error"));
            return;
        }

        $inventory->addItem($duplicate);
        $sender->sendMessage(GlaciaUtils::format("dupe.success", ["name" => $item->getName(), "count" => $item->getCount()], "success"));
    }

    public function getPermission(): string {
        return "glacia.dupe";
    }
}