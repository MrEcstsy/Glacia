<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\auctionhouse;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\MartianUtilities\utils\ItemUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class AuctionHouseSellConfirmScreen extends BaseScreen {

    private SimpleForm $form;

    public function __construct(private string $encodedItem, private int $price, private int $fee)
    {
        $this->form = new SimpleForm(function(Player $player, $data): void {
            if ($data !== 0) {
                return;
            }

            $session = Loader::getPlayerManager()->getSession($player);
            if ($session === null) {
                $player->sendMessage(C::colorize("&r&cCould not load your session."));
                return;
            }

            $manager = Loader::getAuctionHouseManager();
            if (!$manager->canCreateListing($player)) {
                $player->sendMessage(C::colorize(
                    "&r&cYou have reached your auction listing limit of &f" . $manager->getMaxListingsFor($player) . "&r&c."
                ));
                return;
            }

            $hand = $player->getInventory()->getItemInHand();
            if ($hand->isNull() || ItemUtils::encodeItem($hand) !== $this->encodedItem) {
                $player->sendMessage(C::colorize("&r&cThe item in your hand changed before confirmation."));
                return;
            }

            $fee = (int) ceil($manager->calculateSellFee($this->price, $player) * RuneManager::getAuctionFeeMultiplier($player));
            if ($session->getBalance() < $fee) {
                $player->sendMessage(C::colorize("&r&cYou do not have enough money to pay the listing fee."));
                return;
            }

            $session->removeBalance($fee);
            $manager->createListing($player, clone $hand, $this->price);
            $player->getInventory()->setItemInHand(VanillaItems::AIR());
            $player->sendMessage(C::colorize("&r&aYour item has been listed for &f$" . number_format($this->price) . "&a."));
        });

        $item = ItemUtils::decodeItem($this->encodedItem);
        $this->form->setTitle(C::colorize("&r&8Confirm Listing"));
        $this->form->setContent(C::colorize(
            "&r&fItem: &e" . $item->getName() . "\n" .
            "&r&fPrice: &a$" . number_format($this->price) . "\n" .
            "&r&fListing Fee: &c$" . number_format($this->fee) . "\n" .
            "&r&fDuration: &e" . \ecstsy\MartianUtilities\utils\GeneralUtils::translateTime(Loader::getAuctionHouseManager()->getListingDuration())
        ));
        $this->form->addButton(C::colorize("&r&l&aConfirm"));
        $this->form->addButton(C::colorize("&r&l&cCancel"));
    }

    public function getForm(): SimpleForm
    {
        return $this->form;
    }

    public static function display(Player $player, string $encodedItem, int $price, int $fee): void
    {
        $player->sendForm((new self($encodedItem, $price, $fee))->getForm());
    }
}
