<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\enchantments\manager\EnchantEffectManager;
use pocketmine\command\CommandSender;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ReapplyCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $air = VanillaItems::AIR();
        $armorInventory = $sender->getArmorInventory();

        foreach ($armorInventory->getContents() as $slot => $item) {
            if ($item->isNull()) {
                continue;
            }

            $armorInventory->setItem($slot, clone $air);
            $armorInventory->setItem($slot, $item);
        }

        $inventory = $sender->getInventory();
        $heldSlot = $inventory->getHeldItemIndex();
        $heldItem = $inventory->getItem($heldSlot);
        if (!$heldItem->isNull()) {
            EnchantEffectManager::updateHeldItemEffects($sender, $heldItem, $air);
            $inventory->setItem($heldSlot, clone $air);
            $inventory->setItem($heldSlot, $heldItem);
            EnchantEffectManager::updateHeldItemEffects($sender, $air, $heldItem);
        }

        $sender->sendMessage(C::colorize("&r&aReapplied your equipped armor and held item effects."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
