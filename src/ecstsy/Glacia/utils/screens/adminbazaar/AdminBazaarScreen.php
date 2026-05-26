<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\adminbazaar;

use ecstsy\Glacia\server\adminbazaar\AdminBazaarManager;
use ecstsy\Glacia\utils\GlaciaUtils;
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
        $menu->setName(GlaciaUtils::format("adminbazaar.menu.title"));

        $inventory = $menu->getInventory();
        $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::PURPLE())->asItem()->setCustomName("");
        foreach ([1, 2, 3, 5, 6, 7] as $slot) {
            $inventory->setItem($slot, clone $pane);
        }

        $info = VanillaItems::BOOK();
        $info->setCustomName(GlaciaUtils::format("adminbazaar.menu.info.name"));
        $info->setLore([
            GlaciaUtils::format("adminbazaar.menu.info.line1"),
            GlaciaUtils::format("adminbazaar.menu.info.line2"),
            GlaciaUtils::format("adminbazaar.menu.info.line3"),
            "",
            GlaciaUtils::format("adminbazaar.menu.info.next_rotation", [
                "time" => GeneralUtils::translateTime(AdminBazaarManager::getTimeUntilRefresh()),
            ]),
        ]);
        $inventory->setItem(self::INFO_SLOT, $info);

        $listing = AdminBazaarManager::getActiveListing();
        if ($listing === null || AdminBazaarManager::getTimeUntilExpiry() <= 0) {
            $waiting = VanillaBlocks::BARRIER()->asItem();
            $waiting->setCustomName(GlaciaUtils::format("adminbazaar.menu.empty.name"));
            $waiting->setLore([
                GlaciaUtils::format("adminbazaar.menu.empty.line1"),
                GlaciaUtils::format("adminbazaar.menu.empty.line2", [
                    "time" => GeneralUtils::translateTime(AdminBazaarManager::getTimeUntilRefresh()),
                ]),
            ]);
            $inventory->setItem(self::CENTER_SLOT, $waiting);
            $inventory->setItem(self::BUY_SLOT, VanillaItems::REDSTONE_DUST()->setCustomName(GlaciaUtils::format("adminbazaar.menu.empty.buy")));
        } else {
            $item = $listing["item"] ?? null;
            if (!$item instanceof \pocketmine\item\Item) {
                $item = VanillaItems::CHEST();
            }

            $item = clone $item;
            $lore = $item->getLore();
            $lore[] = "";
            $lore[] = GlaciaUtils::format("adminbazaar.menu.listing.price", [
                "price" => AdminBazaarManager::formatPrice(
                    (int) ($listing["price"]["amount"] ?? 0),
                    (string) ($listing["price"]["currency"] ?? "money")
                ),
            ]);
            $lore[] = GlaciaUtils::format("adminbazaar.menu.listing.expires", [
                "time" => GeneralUtils::translateTime(AdminBazaarManager::getTimeUntilExpiry()),
            ]);
            $lore[] = GlaciaUtils::format("adminbazaar.menu.listing.click");
            $item->setLore($lore);
            $inventory->setItem(self::CENTER_SLOT, $item);

            $buy = StringToItemParser::getInstance()->parse("emerald") ?? VanillaItems::EMERALD();
            $buy->setCustomName(GlaciaUtils::format("adminbazaar.menu.buy.name"));
            $buy->setLore([
                GlaciaUtils::format("adminbazaar.menu.buy.line1"),
                GlaciaUtils::format("adminbazaar.menu.buy.line2", [
                    "price" => AdminBazaarManager::formatPrice(
                        (int) ($listing["price"]["amount"] ?? 0),
                        (string) ($listing["price"]["currency"] ?? "money")
                    ),
                ]),
            ]);
            $inventory->setItem(self::BUY_SLOT, $buy);
        }

        $menu->setListener(InvMenu::readonly(static function (DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            if ($slot !== self::BUY_SLOT && $slot !== self::CENTER_SLOT) {
                return;
            }

            if (AdminBazaarManager::getActiveListing() === null || AdminBazaarManager::getTimeUntilExpiry() <= 0) {
                return;
            }

            $player = $transaction->getPlayer();
            $player->removeCurrentWindow();
            $transaction->then(static function (Player $player): void {
                AdminBazaarPurchaseConfirmScreen::queueDisplay($player);
            });
        }));

        $menu->send($player);
    }
}
