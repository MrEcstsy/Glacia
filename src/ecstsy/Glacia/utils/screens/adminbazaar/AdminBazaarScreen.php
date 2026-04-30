<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\adminbazaar;

use ecstsy\Glacia\server\adminbazaar\AdminBazaarManager;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AdminBazaarScreen
{
    private const INFO_SLOT = 0;
    private const CENTER_SLOT = 4;
    private const BUY_SLOT = 8;

    public static function display(Player $player) : void
    {
        $menu = CustomSizedInvMenu::create(9);
        $menu->setName(C::colorize("&r&8Admin Bazaar"));

        $inventory = $menu->getInventory();
        $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::PURPLE())->asItem()->setCustomName("");
        foreach ([1, 2, 3, 5, 6, 7] as $slot) {
            $inventory->setItem($slot, clone $pane);
        }

        $info = VanillaItems::BOOK();
        $info->setCustomName(C::colorize("&r&l&dAdmin Bazaar"));
        $info->setLore([
            C::colorize("&r&7A rotating admin-curated deal."),
            C::colorize("&r&7Each listing stays up for a limited time"),
            C::colorize("&r&7and can only be purchased once."),
            "",
            C::colorize("&r&7Next rotation: &f" . GeneralUtils::translateTime(AdminBazaarManager::getTimeUntilRefresh())),
        ]);
        $inventory->setItem(self::INFO_SLOT, $info);

        $listing = AdminBazaarManager::getActiveListing();
        if ($listing === null || AdminBazaarManager::getTimeUntilExpiry() <= 0) {
            $waiting = VanillaBlocks::BARRIER()->asItem();
            $waiting->setCustomName(C::colorize("&r&l&cNo Active Listing"));
            $waiting->setLore([
                C::colorize("&r&7The next Admin Bazaar listing"),
                C::colorize("&r&7arrives in &f" . GeneralUtils::translateTime(AdminBazaarManager::getTimeUntilRefresh())),
            ]);
            $inventory->setItem(self::CENTER_SLOT, $waiting);
            $inventory->setItem(self::BUY_SLOT, VanillaItems::REDSTONE_DUST()->setCustomName(C::colorize("&r&cUnavailable")));
        } else {
            $item = $listing["item"] ?? null;
            if (!$item instanceof \pocketmine\item\Item) {
                $item = VanillaItems::CHEST();
            }

            $item = clone $item;
            $lore = $item->getLore();
            $lore[] = "";
            $lore[] = C::colorize("&r&dPrice: &f" . AdminBazaarManager::formatPrice(
                (int) ($listing["price"]["amount"] ?? 0),
                (string) ($listing["price"]["currency"] ?? "money")
            ));
            $lore[] = C::colorize("&r&dExpires In: &f" . GeneralUtils::translateTime(AdminBazaarManager::getTimeUntilExpiry()));
            $lore[] = C::colorize("&r&7Click the buy button to claim it.");
            $item->setLore($lore);
            $inventory->setItem(self::CENTER_SLOT, $item);

            $buy = StringToItemParser::getInstance()->parse("emerald") ?? VanillaItems::EMERALD();
            $buy->setCustomName(C::colorize("&r&l&aPurchase Listing"));
            $buy->setLore([
                C::colorize("&r&7Buy this listing for"),
                C::colorize("&r&f" . AdminBazaarManager::formatPrice(
                    (int) ($listing["price"]["amount"] ?? 0),
                    (string) ($listing["price"]["currency"] ?? "money")
                )),
            ]);
            $inventory->setItem(self::BUY_SLOT, $buy);
        }

        $menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction) : void {
            if ($transaction->getAction()->getSlot() !== self::BUY_SLOT) {
                return;
            }

            AdminBazaarManager::purchase($transaction->getPlayer());
        }));

        $menu->send($player);
    }
}
