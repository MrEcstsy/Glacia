<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\quests;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class QuestShopScreen
{
    /** @var array<int, string> */
    private array $slotMap = [];
    private InvMenu $menu;

    private function __construct(private Player $player)
    {
        $this->menu = CustomSizedInvMenu::create(27);
        $this->menu->setName(C::colorize("&r&l&6Quest Shop"));
        $this->build();
        $this->menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            if ($slot === 22) {
                $player->removeCurrentWindow();
                $transaction->then(static function(Player $player): void {
                    QuestsScreen::display($player);
                });
                return;
            }

            $offerId = $this->slotMap[$slot] ?? null;
            if ($offerId === null) {
                return;
            }

            $player->removeCurrentWindow();
            $transaction->then(static function(Player $player) use ($offerId): void {
                QuestShopPurchaseConfirmScreen::display($player, $offerId);
            });
        }));
    }

    public static function display(Player $player): void
    {
        (new self($player))->menu->send($player);
    }

    private function build(): void
    {
        $manager = Loader::getQuestBoardManager();
        $inventory = $this->menu->getInventory();
        $inventory->clearAll();
        $this->slotMap = [];

        $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" ");
        foreach (range(0, 26) as $slot) {
            $inventory->setItem($slot, clone $pane);
        }

        $inventory->setItem(4, VanillaItems::NETHER_STAR()->setCustomName(C::colorize("&r&l&bQuest Currency"))->setLore([
            C::colorize("&r&7Quest Points: &6" . number_format($manager->getQuestPoints($this->player))),
            C::colorize("&r&7Refreshes In: &b" . GeneralUtils::translateTime($manager->getShopRefreshRemaining())),
        ]));
        $inventory->setItem(22, VanillaItems::ARROW()->setCustomName(C::colorize("&r&l&cBack to Quests")));

        $displaySlots = [10, 11, 12, 13, 14, 15, 16];
        foreach ($manager->getShopRotation() as $index => $offer) {
            $slot = $displaySlots[$index] ?? null;
            if ($slot === null) {
                break;
            }

            $item = \ecstsy\Glacia\utils\RewardResolver::resolveItem((array) $offer["reward"], $this->player);
            if ($item === null) {
                continue;
            }

            $preview = clone $item;
            $displayName = trim((string) ($offer["display"] ?? ""));
            if ($displayName !== "") {
                $preview->setCustomName(C::colorize($displayName));
            }
            $lore = $preview->getLore();
            if ($lore !== []) {
                $lore[] = "";
            }
            $lore[] = C::colorize("&r&7Cost: &6" . number_format((int) $offer["cost"]) . " &7Quest Points");
            $lore[] = C::colorize("&r&bClick to purchase");
            $preview->setLore($lore);

            $inventory->setItem($slot, $preview);
            $this->slotMap[$slot] = (string) $offer["id"];
        }
    }
}
