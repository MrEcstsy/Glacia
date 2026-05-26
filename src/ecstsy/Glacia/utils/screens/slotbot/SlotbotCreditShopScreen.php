<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\slotbot;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\slotbot\SlotbotRewardRepository;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use ecstsy\Glacia\utils\PlayerUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SlotbotCreditShopScreen extends BaseScreen {

    private const TAG_BUY = "glacia_slotbot_credit_buy";

    private InvMenu $menu;

    public function __construct(private Player $player)
    {
        $this->menu = CustomSizedInvMenu::create(54);
        $this->menu->setName(C::colorize("&r&l&dSlotbot &8> &r&7Credit Shop"));

        $this->render();
        $this->menu->setListener(InvMenu::readonly(
            function (DeterministicInvMenuTransaction $transaction): void {
                $this->handle($transaction);
            }
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

    private function render(): void
    {
        $inventory = $this->menu->getInventory();
        $inventory->clearAll();

        $glass = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" ");
        foreach (range(0, 53) as $slot) {
            $inventory->setItem($slot, clone $glass);
        }

        $credits = Loader::getPlayerManager()->getSession($this->player)?->getSlotCredits() ?? 0;
        $offer = SlotbotRewardRepository::getCurrentCreditShopOffer($this->player);
        $rotationSeconds = SlotbotRewardRepository::getCreditShopRotationSeconds();

        $inventory->setItem(4, VanillaItems::CLOCK()
            ->setCustomName(C::colorize("&r&l&eCredits: &f" . number_format($credits)))
            ->setLore([
                C::colorize("&r&7Spend slotbot credits on the"),
                C::colorize("&r&7current rotating offer.")
            ])
        );

        $inventory->setItem(13, VanillaItems::BOOK()
            ->setCustomName(C::colorize("&r&l&bRotation"))
            ->setLore([
                C::colorize("&r&7Refreshes every &f" . GeneralUtils::translateTime($rotationSeconds)),
                C::colorize("&r&7All players see the same offer."),
                C::colorize("&r&7Next swap in &f" . GeneralUtils::translateTime($offer["seconds_remaining"] ?? 0)),
            ])
        );

        if ($offer === null) {
            $inventory->setItem(31, VanillaItems::BARRIER()
                ->setCustomName(C::colorize("&r&cNo Active Offer"))
                ->setLore([
                    C::colorize("&r&7There are no valid credit shop items"),
                    C::colorize("&r&7configured in &fslotbot.yml&r&7.")
                ])
            );
            return;
        }

        $inventory->setItem(31, $this->buildOfferDisplayItem(
            clone $offer["item"],
            (int) $offer["price"],
            (int) $offer["seconds_remaining"]
        ));
    }

    private function handle(DeterministicInvMenuTransaction $transaction): void
    {
        $player = $transaction->getPlayer();
        $clicked = $transaction->getItemClicked();
        $slot = $transaction->getAction()->getSlot();
        if ($slot !== 31 && $clicked->getNamedTag()->getTag(self::TAG_BUY) === null) {
            return;
        }

        $offer = SlotbotRewardRepository::getCurrentCreditShopOffer($player);
        if ($offer === null) {
            $player->sendMessage(C::colorize("&r&cThere is no active credit shop offer right now."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $player->removeCurrentWindow();
        $transaction->then(static function (Player $player) use ($offer): void {
            SlotbotCreditShopPurchaseConfirmScreen::queueDisplay($player, (string) $offer["key"]);
        });
    }

    private function buildOfferDisplayItem(Item $item, int $price, int $secondsRemaining): Item
    {
        $tag = $item->getNamedTag();
        $tag->setByte(self::TAG_BUY, 1);
        $item->setNamedTag($tag);

        $lore = $item->getLore();
        $lore[] = C::colorize("&r");
        $lore[] = C::colorize("&r&6Price: &f" . number_format($price) . " Slot Credits");
        $lore[] = C::colorize("&r&dRotates In: &f" . GeneralUtils::translateTime($secondsRemaining));
        $lore[] = C::colorize("&r&aClick to purchase.");
        $item->setLore($lore);

        return $item;
    }
}
