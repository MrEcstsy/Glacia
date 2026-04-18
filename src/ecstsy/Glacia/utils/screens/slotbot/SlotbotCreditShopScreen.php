<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\slotbot;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SlotbotCreditShopScreen extends BaseScreen {

    private InvMenu $menu;

    public function __construct(private Player $player)
    {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize("&r&l&dSlotbot &8> &r&7Credit Shop"));

        $inventory = $this->menu->getInventory();
        $glass = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" ");
        foreach (range(0, 53) as $slot) {
            $inventory->setItem($slot, clone $glass);
        }

        $credits = Loader::getPlayerManager()->getSession($player)?->getSlotCredits() ?? 0;

        $inventory->setItem(13, VanillaItems::CLOCK()
            ->setCustomName(C::colorize("&r&l&eCredits: &f" . number_format($credits)))
            ->setLore([
                C::colorize("&r&7This shop is reserved for slotbot credits."),
                C::colorize("&r&7The rotation/purchase system is the next piece to wire.")
            ])
        );

        $inventory->setItem(31, VanillaItems::BOOK()
            ->setCustomName(C::colorize("&r&l&bComing Soon"))
            ->setLore([
                C::colorize("&r&7The base slotbot system is now in place."),
                C::colorize("&r&7Credit shop rotation will build on this.")
            ])
        );

        $this->menu->setListener(InvMenu::readonly(
            fn(DeterministicInvMenuTransaction $transaction): bool => false
        ));
    }

    public function display(Player $player): void
    {
        $this->menu->send($player);
    }

    public function getMenu(): ?InvMenu
    {
        return $this->menu;
    }
}
