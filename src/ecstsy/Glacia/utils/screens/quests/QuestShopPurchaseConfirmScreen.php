<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\quests;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\RewardResolver;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class QuestShopPurchaseConfirmScreen
{
    public static function display(Player $player, string $offerId): void
    {
        $manager = Loader::getQuestBoardManager();
        $definition = null;
        foreach ($manager->getShopRotation() as $offer) {
            if ((string) ($offer["id"] ?? "") === $offerId) {
                $definition = $offer;
                break;
            }
        }

        if (!is_array($definition)) {
            $player->sendMessage(C::colorize("&r&cThat quest shop offer is no longer available."));
            QuestShopScreen::display($player);
            return;
        }

        $item = RewardResolver::resolveItem((array) ($definition["reward"] ?? []), $player);
        $displayName = trim((string) ($definition["display"] ?? ""));
        $itemName = "Unknown Reward";
        if ($item !== null) {
            $customName = trim($item->getCustomName());
            $itemName = $customName !== "" ? $customName : $item->getName();
        } elseif ($displayName !== "") {
            $itemName = C::colorize($displayName);
        }
        $cost = max(1, (int) ($definition["cost"] ?? 1));

        $form = new SimpleForm(function(Player $player, ?int $data) use ($offerId): void {
            if ($data === 0) {
                Loader::getQuestBoardManager()->purchaseShopOffer($player, $offerId);
            }

            QuestShopScreen::display($player);
        });

        $form->setTitle(C::colorize("&r&8Confirm Purchase"));
        $form->setContent(C::colorize(
            "&r&7Reward: " . $itemName . "\n" .
            "&r&7Cost: &6" . number_format($cost) . " &7Quest Points\n" .
            "&r&7Your Points: &b" . number_format($manager->getQuestPoints($player)) . "\n\n" .
            "&r&7Confirm this quest shop purchase?"
        ));
        $form->addButton(C::colorize("&r&aConfirm"));
        $form->addButton(C::colorize("&r&cCancel"));
        $player->sendForm($form);
    }
}
