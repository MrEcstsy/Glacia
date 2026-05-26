<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\enchanter;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchanter\EnchanterRepository;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\CustomForm;

final class EnchanterConfirmScreen extends BaseScreen {

    public CustomForm $form;

    public function __construct(array $bookData)
    {
        $group = (string) $bookData["group"];
        $price = (int) $bookData["price"];
        $displayName = EnchanterRepository::createRewardItem($group)->getCustomName();

        $this->form = new CustomForm(function(Player $player, $data) use ($group, $price, $displayName): void {
            if ($data === null) {
                return;
            }

            $amount = (int) $data[1];
            $buyMax = (bool) $data[2];
            $customAmount = trim((string) $data[3]);

            $item = EnchanterRepository::createRewardItem($group);

            if ($buyMax) {
                $amount = min(self::getMaxFit($player, $item), self::getMaxAffordable($player, $price));
            } elseif ($customAmount !== "" && is_numeric($customAmount)) {
                $amount = (int) $customAmount;
            }

            if ($amount <= 0) {
                return;
            }

            $maxFit = self::getMaxFit($player, $item);
            if ($maxFit <= 0) {
                $player->sendMessage(C::colorize("&cYou do not have enough inventory space."));
                return;
            }

            $maxAffordable = self::getMaxAffordable($player, $price);
            if ($maxAffordable <= 0) {
                $player->sendMessage(C::colorize("&cYou do not have enough XP."));
                return;
            }

            $amount = min($amount, $maxFit, $maxAffordable);
            $totalCost = $price * $amount;
            $currentXp = $player->getXpManager()->getCurrentTotalXp();

            if ($currentXp < $totalCost) {
                $player->sendMessage(C::colorize("&cYou do not have enough XP."));
                return;
            }

            if ($totalCost > 0) {
                $player->getXpManager()->setCurrentTotalXp($currentXp - $totalCost);
                Loader::getChallengeManager()->incrementProgress($player, "xp_spent_enchanter", $totalCost);
            }
            $item->setCount($amount);
            $player->getInventory()->addItem($item);

            $player->sendMessage(C::colorize("&aPurchased x$amount " . C::clean($displayName) . "&a for " . number_format($totalCost) . " XP"));
        });

        $this->form->setTitle(C::colorize("&r&8Confirm Purchase"));
        $this->form->addLabel(C::colorize(
            $displayName . "\n" .
            "&7Price: &b" . number_format($price) . " XP &7each"
        ));
        $this->form->addSlider("Amount", 1, 64, 1, 1);
        $this->form->addToggle("Buy Max", false);
        $this->form->addInput("Or enter exact amount", "Leave blank to use slider");
    }

    public static function displayForm(Player $player, array $bookData): void
    {
        $player->sendForm((new self($bookData))->getForm());
    }

    public function getForm(): CustomForm
    {
        return $this->form;
    }

    private static function getMaxFit(Player $player, Item $item): int
    {
        $inv = $player->getInventory();
        $count = 0;

        for ($slot = 0; $slot < $inv->getSize(); $slot++) {
            $slotItem = $inv->getItem($slot);

            if ($slotItem->isNull()) {
                $count += $item->getMaxStackSize();
            } elseif ($slotItem->canStackWith($item)) {
                $count += ($item->getMaxStackSize() - $slotItem->getCount());
            }
        }

        return $count;
    }

    private static function getMaxAffordable(Player $player, int $price): int
    {
        if ($price <= 0) {
            return 64;
        }

        return intdiv($player->getXpManager()->getCurrentTotalXp(), $price);
    }
}
