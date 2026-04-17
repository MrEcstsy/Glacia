<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\blackmarket;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class BlackMarketConfirmScreen extends BaseScreen {

    private SimpleForm $form;

    public function __construct(private int $amount) {
        $this->form = new SimpleForm(function(Player $player, $data): void {
            if ($data === null) {
                return;
            }

            $bah = Loader::getBlackMarketAuctionInstance();

            switch ($data) {
                case 0:
                    if (!$bah->isEnabled() || !$bah->hasStarted()) {
                        $player->sendMessage(C::colorize("&r&cThere is no active auction right now."));
                        return;
                    }

                    $session = PlayerManager::getInstance()->getSession($player);
                    if ($session === null) {
                        $player->sendMessage(C::colorize("&r&cCould not load your session."));
                        return;
                    }

                    $updatedAmount = $bah->getNextBidAmount();

                    if ($session->getBalance() < $updatedAmount) {
                        $player->sendMessage(C::colorize("&r&cYou do not have enough money to bid &f$" . number_format($updatedAmount) . "&c."));
                        return;
                    }

                    if (!$bah->placeBid($player)) {
                        $player->sendMessage(C::colorize("&r&cFailed to place your bid."));
                        return;
                    }

                    $player->sendMessage(C::colorize("&r&aYou successfully bid &f$" . number_format($updatedAmount) . "&a on the Black Market Auction."));
                    break;

                case 1:
                    $player->sendMessage(C::colorize("&r&cYou cancelled your bid."));
                    break;
            }
        });

        $this->form->setTitle(C::colorize("&r&8Bid for $" . number_format($this->amount)));
        $this->form->setContent(C::colorize("&r&7Are you sure you want to bid &a$" . number_format($this->amount) . "&7?"));
        $this->form->addButton(C::colorize("&r&l&aConfirm"));
        $this->form->addButton(C::colorize("&r&l&cCancel"));
    }

    public function getForm(): SimpleForm {
        return $this->form;
    }

    public static function display(Player $player): void {
        $bah = Loader::getBlackMarketAuctionInstance();

        if (!$bah->isEnabled() || !$bah->hasStarted()) {
            $player->sendMessage(C::colorize("&r&cThere is no active auction right now."));
            return;
        }

        $amount = $bah->getNextBidAmount();
        $player->sendForm((new self($amount))->getForm());
    }
}