<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\auctionhouse;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\utils\screens\auctionhouse\AuctionHouseSellConfirmScreen;
use ecstsy\MartianUtilities\utils\ItemUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AuctionSellSubCommand extends BaseSubCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("price"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $item = $sender->getInventory()->getItemInHand();
        if ($item->isNull()) {
            $sender->sendMessage(C::colorize("&r&cHold the item you want to list in your hand."));
            return;
        }

        $priceInput = strtolower(trim((string) ($args["price"] ?? "")));
        if ($priceInput === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /ah sell <price>"));
            return;
        }

        $price = is_numeric($priceInput) ? (int) $priceInput : (int) PlayerUtils::parseShorthandAmount($priceInput);
        if ($price <= 0) {
            $sender->sendMessage(C::colorize("&r&cEnter a valid price."));
            return;
        }

        $manager = Loader::getAuctionHouseManager();
        if ($price > $manager->getMaxPrice()) {
            $sender->sendMessage(C::colorize("&r&cThat price is too high."));
            return;
        }

        if (!$manager->canCreateListing($sender)) {
            $sender->sendMessage(C::colorize(
                "&r&cYou have reached your auction listing limit of &f" . $manager->getMaxListingsFor($sender) . "&r&c."
            ));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cCould not load your player session."));
            return;
        }

        $fee = (int) ceil($manager->calculateSellFee($price, $sender) * RuneManager::getAuctionFeeMultiplier($sender));
        if ($session->getBalance() < $fee) {
            $sender->sendMessage(C::colorize("&r&cYou need &f$" . number_format($fee) . "&c to pay the listing fee."));
            return;
        }

        AuctionHouseSellConfirmScreen::display($sender, ItemUtils::encodeItem($item), $price, $fee);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
