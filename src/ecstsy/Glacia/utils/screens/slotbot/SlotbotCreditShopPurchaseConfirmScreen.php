<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\slotbot;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\slotbot\SlotbotRewardRepository;
use ecstsy\Glacia\server\watchdogs\ChunkWatchdogManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class SlotbotCreditShopPurchaseConfirmScreen
{
    public static function queueDisplay(Player $player, string $expectedOfferKey): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(
            new ClosureTask(static function () use ($player, $expectedOfferKey): void {
                if ($player->isConnected()) {
                    self::display($player, $expectedOfferKey);
                }
            }),
            1
        );
    }

    public static function display(Player $player, string $expectedOfferKey): void
    {
        $offer = SlotbotRewardRepository::getCurrentCreditShopOffer($player);
        if ($offer === null || (string) $offer["key"] !== $expectedOfferKey) {
            $player->sendMessage(C::colorize("&r&cThat shop offer changed before you confirmed."));
            (new SlotbotCreditShopScreen($player))->display($player);
            return;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        $credits = $session?->getSlotCredits() ?? 0;
        $price = ChunkWatchdogManager::getFactionWitchSlotbotPrice($player, (int) $offer["price"]);
        $itemName = $offer["item"]->getCustomName() !== ""
            ? $offer["item"]->getCustomName()
            : $offer["item"]->getName();

        $form = new SimpleForm(function (Player $player, ?int $data) use ($expectedOfferKey): void {
            if ($data === null) {
                return;
            }

            if ($data === 0) {
                self::purchase($player, $expectedOfferKey);
                return;
            }

            (new SlotbotCreditShopScreen($player))->display($player);
        });

        $form->setTitle(C::colorize("&r&l&eConfirm Purchase"));
        $form->setContent(C::colorize(implode("\n", [
            "&r&7Buy &f" . $itemName . "&7?",
            "",
            "&r&6Price: &f" . number_format($price) . " Slot Credits",
            "&r&eYour Credits: &f" . number_format($credits),
            "&r&dOffer Rotates In: &f" . GeneralUtils::translateTime((int) $offer["seconds_remaining"]),
        ])));
        $form->addButton(C::colorize("&r&aConfirm Purchase"));
        $form->addButton(C::colorize("&r&cCancel"));
        $player->sendForm($form);
    }

    private static function purchase(Player $player, string $expectedOfferKey): void
    {
        $offer = SlotbotRewardRepository::getCurrentCreditShopOffer($player);
        if ($offer === null || (string) $offer["key"] !== $expectedOfferKey) {
            $player->sendMessage(C::colorize("&r&cThat shop offer changed before purchase completed."));
            (new SlotbotCreditShopScreen($player))->display($player);
            return;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            $player->sendMessage(C::colorize("&r&cYour player session could not be found."));
            return;
        }

        $price = ChunkWatchdogManager::getFactionWitchSlotbotPrice($player, (int) $offer["price"]);
        if (!$session->removeSlotCredits($price)) {
            $player->sendMessage(C::colorize("&r&cYou do not have enough slotbot credits for that purchase."));
            (new SlotbotCreditShopScreen($player))->display($player);
            return;
        }

        $item = clone $offer["item"];
        foreach ($player->getInventory()->addItem($item) as $leftover) {
            $player->getWorld()->dropItem($player->getPosition(), $leftover);
        }

        $itemName = $item->getCustomName() !== ""
            ? $item->getCustomName()
            : $item->getName();
        $player->sendMessage(C::colorize("&r&aPurchased &f" . $itemName . "&r&a for &f" . number_format($price) . " &aSlot Credits."));
        (new SlotbotCreditShopScreen($player))->display($player);
    }
}
