<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\adminbazaar;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\adminbazaar\AdminBazaarManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use Vecnavium\FormsUI\SimpleForm;

final class AdminBazaarPurchaseConfirmScreen
{
    public static function queueDisplay(Player $player): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(
            new ClosureTask(static function () use ($player): void {
                if ($player->isConnected()) {
                    self::display($player);
                }
            }),
            1
        );
    }

    public static function display(Player $player): void
    {
        $listing = AdminBazaarManager::getActiveListing();
        if ($listing === null || AdminBazaarManager::getTimeUntilExpiry() <= 0) {
            $player->sendMessage(GlaciaUtils::format("adminbazaar.error.no_active_listing"));
            AdminBazaarScreen::display($player);
            return;
        }

        $form = new SimpleForm(function (Player $player, ?int $data): void {
            if ($data === null) {
                return;
            }

            if ($data === 0) {
                AdminBazaarManager::purchase($player);
            }

            AdminBazaarScreen::display($player);
        });

        $form->setTitle(GlaciaUtils::format("adminbazaar.confirm.title"));
        $form->setContent(GlaciaUtils::format("adminbazaar.confirm.content", [
            "item" => (string) ($listing["name"] ?? "Unknown"),
            "price" => AdminBazaarManager::formatPrice(
                (int) ($listing["price"]["amount"] ?? 0),
                (string) ($listing["price"]["currency"] ?? "money")
            ),
            "time" => GeneralUtils::translateTime(AdminBazaarManager::getTimeUntilExpiry()),
        ]));
        $form->addButton(GlaciaUtils::format("adminbazaar.confirm.button.confirm"));
        $form->addButton(GlaciaUtils::format("adminbazaar.confirm.button.cancel"));
        $player->sendForm($form);
    }
}
