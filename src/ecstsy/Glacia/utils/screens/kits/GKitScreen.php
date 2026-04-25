<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\kits;

use ecstsy\Glacia\server\gkits\GKitRepository;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GKitScreen extends BaseScreen
{
    public InvMenu $invMenu;

    public function __construct()
    {
        $this->invMenu = InvMenu::create(InvMenu::TYPE_CHEST);
        $this->invMenu->setName(C::colorize("&r&8Glacial GKits"));
        $this->build();

        $this->invMenu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            foreach (GKitRepository::getGKits() as $kit) {
                if ((int) ($kit["slot"] ?? -1) !== $slot) {
                    continue;
                }

                PlayerUtils::playSound($player, "random.click");
                if (GKitRepository::claim($player, $kit)) {
                    $this->build($player);
                } else {
                    PlayerUtils::playSound($player, "note.bass");
                }
                return;
            }
        }));
    }

    public static function display(Player $player): void
    {
        $screen = new self();
        $screen->build($player);
        $screen->getMenu()->send($player);
        PlayerUtils::playSound($player, "random.chestopen");
    }

    public function getMenu(): InvMenu
    {
        return $this->invMenu;
    }

    private function build(?Player $player = null): void
    {
        $inv = $this->invMenu->getInventory();
        $inv->clearAll();

        for ($slot = 0; $slot < $inv->getSize(); $slot++) {
            $color = in_array($slot, [0, 4, 8, 9, 17, 18, 22, 26], true) ? DyeColor::CYAN() : DyeColor::BLACK();
            $inv->setItem($slot, VanillaBlocks::STAINED_GLASS_PANE()->setColor($color)->asItem()->setCustomName(" "));
        }

        foreach (GKitRepository::getGKits() as $kit) {
            $slot = (int) ($kit["slot"] ?? -1);
            if ($slot < 0 || $slot >= $inv->getSize()) {
                continue;
            }

            $inv->setItem($slot, GKitRepository::createIcon($kit, $player));
        }
    }
}
