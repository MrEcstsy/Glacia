<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\kits;

use ecstsy\Glacia\server\kits\KitRepository;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class KitScreen extends BaseScreen {

    public InvMenu $invMenu;

    public function __construct()
    {
        $this->invMenu = InvMenu::create(InvMenu::TYPE_CHEST);
        $this->invMenu->setName(C::colorize("&r&8Rank Kits"));
        $this->build();

        $this->invMenu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            foreach (KitRepository::getRankKits() as $kit) {
                if ((int) $kit["slot"] !== $slot) {
                    continue;
                }

                if (KitRepository::claimKit($player, $kit)) {
                    $this->build($player);
                }
                return;
            }
        }));
    }

    public static function display(Player $player): void {
        $screen = new self();
        $screen->build($player);
        $screen->getMenu()->send($player);
    }

    public function getMenu(): InvMenu {
        return $this->invMenu;
    }

    private function build(?Player $player = null): void {
        $inv = $this->invMenu->getInventory();
        $inv->clearAll();

        for ($slot = 0; $slot < $inv->getSize(); $slot++) {
            $inv->setItem($slot, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" "));
        }

        foreach (KitRepository::getRankKits() as $kit) {
            $slot = (int) $kit["slot"];
            if ($slot < 0 || $slot >= $inv->getSize()) {
                continue;
            }

            $inv->setItem($slot, KitRepository::createIcon($kit, $player));
        }
    }
}
