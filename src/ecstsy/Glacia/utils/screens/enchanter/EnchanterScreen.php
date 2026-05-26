<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\enchanter;

use ecstsy\Glacia\server\enchanter\EnchanterRepository;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\player\Player;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use pocketmine\utils\TextFormat as C;

final class EnchanterScreen extends BaseScreen {

    public InvMenu $invMenu;

    public function __construct(private ?Player $viewer = null)
    {
        $this->invMenu = CustomSizedInvMenu::create(27);
        $this->invMenu->setName(C::colorize("&r&l&8Glacia Enchanter"));
        $this->invMenu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            $bookData = EnchanterRepository::getBookDataBySlot($slot, $player);
            if ($bookData === null) {
                return;
            }

            $player->removeCurrentWindow();
            $transaction->then(function() use ($player, $bookData): void {
                EnchanterConfirmScreen::displayForm($player, $bookData);
            });
        }));

        $this->build();
    }

    public function display(Player $player): void {
        (new self($player))->getMenu()->send($player);
    }

    public function getMenu(): InvMenu {
        return $this->invMenu;
    }

    private function build(): void
    {
        $inventory = $this->invMenu->getInventory();
        $filler = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" ");

        for ($slot = 0; $slot < $inventory->getSize(); $slot++) {
            $inventory->setItem($slot, clone $filler);
        }

        foreach (EnchanterRepository::getBooks($this->viewer) as $slot => $bookData) {
            $inventory->setItem($slot, EnchanterRepository::createDisplayItem($bookData["group"], $bookData["price"], $this->viewer));
        }
    }
}
