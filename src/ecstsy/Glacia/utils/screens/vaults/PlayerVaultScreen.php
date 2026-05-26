<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\vaults;

use ecstsy\Glacia\Loader;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\inventory\Inventory;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PlayerVaultScreen
{
    private InvMenu $menu;

    public function __construct(
        private Player $viewer,
        private string $ownerUuid,
        private string $ownerName,
        private int $vaultNumber,
        private bool $readonly = false
    ) {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $isOwnerView = $this->viewer->getUniqueId()->toString() === $this->ownerUuid;
        $title = $isOwnerView ? "&r&8Player Vault #{$vaultNumber}" : "&r&8{$ownerName}'s Vault #{$vaultNumber}";
        $this->menu->setName(C::colorize($title));

        Loader::getPlayerVaultManager()->populateInventory($this->menu->getInventory(), $this->ownerUuid, $this->vaultNumber);

        if ($this->readonly) {
            $this->menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction) : void {
                $transaction->getPlayer()->sendMessage(C::colorize("&r&cThis vault is view-only."));
            }));
            return;
        }

        $this->menu->setInventoryCloseListener(function(Player $player, Inventory $inventory) : void {
            Loader::getPlayerVaultManager()->releaseOpenEditor($this->ownerUuid, $this->vaultNumber, $inventory);
            $player->sendMessage(C::colorize("&r&aSaved {$this->ownerName}'s vault &f#" . $this->vaultNumber . "&a."));
        });
    }

    public function display(Player $player) : void
    {
        $this->menu->send($player, null, function(bool $success) : void {
            if (!$success && !$this->readonly) {
                Loader::getPlayerVaultManager()->abortOpenEditor($this->ownerUuid, $this->vaultNumber);
            }
        });
    }
}
