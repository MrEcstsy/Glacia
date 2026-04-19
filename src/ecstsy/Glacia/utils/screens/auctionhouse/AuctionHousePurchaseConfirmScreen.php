<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\auctionhouse;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class AuctionHousePurchaseConfirmScreen
{
    public static function displayForm(Player $player, string $listingId): void
    {
        $listing = Loader::getAuctionHouseManager()->getListing($listingId);
        if ($listing === null) {
            $player->sendMessage(C::colorize("&r&cThat listing no longer exists."));
            (new AuctionHouseScreen($player))->display($player);
            return;
        }

        $item = ItemUtils::decodeItem((string) $listing["item_data"]);
        $form = new SimpleForm(function(Player $player, ?int $data) use ($listingId): void {
            if ($data === 0) {
                $result = Loader::getAuctionHouseManager()->purchaseListing($player, $listingId);
                if ($result !== "") {
                    $player->sendMessage(C::colorize("&r&c" . $result));
                } else {
                    $player->sendMessage(C::colorize("&r&aPurchase successful."));
                }
            }

            (new AuctionHouseScreen($player))->display($player);
        });

        $form->setTitle(C::colorize("&r&8Confirm Purchase"));
        $form->setContent(C::colorize(
            "&r&fItem: &b" . $item->getName() . "\n" .
            "&r&fPrice: &a$" . number_format((float) $listing["price"], 2) . "\n" .
            "&r&fSeller: &e" . $listing["seller_name"] . "\n\n" .
            "&r&7Confirm this auction purchase?"
        ));
        $form->addButton(C::colorize("&r&aConfirm"));
        $form->addButton(C::colorize("&r&cCancel"));
        $player->sendForm($form);
    }
}
