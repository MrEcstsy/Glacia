<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\itemflip;

use ecstsy\Glacia\server\itemflip\ItemFlipSession;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ItemFlipScreen {

    private InvMenu $menu;
    /** @var string[] */
    private array $choiceOrder;

    public function __construct(
        private ItemFlipSession $session,
        private Player $viewer,
        private Player $opponent
    ) {
        $this->choiceOrder = array_keys(ItemFlipSession::CHOICES);
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize("&r&8Item Flip: &f" . $this->opponent->getName()));
        $this->menu->setListener(function(InvMenuTransaction $transaction): InvMenuTransactionResult {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            if (in_array($slot, $this->session->getOwnOfferSlots($player), true)) {
                return $this->session->handleOfferTransaction($player, $slot, $transaction);
            }

            if ($slot === $this->session->getReadySlot() || in_array($slot, ItemFlipSession::COLOR_SELECTOR_SLOTS, true)) {
                $this->session->handleButtonClick($player, $slot);
                return $transaction->discard();
            }

            return $transaction->discard();
        });
        $this->menu->setInventoryCloseListener(function(Player $player, Inventory $inventory): void {
            $this->session->onClosed($player);
        });
    }

    public function send(): void
    {
        $this->menu->send($this->viewer);
    }

    public function closeForViewer(): void
    {
        if ($this->viewer->getCurrentWindow() === $this->menu->getInventory()) {
            $this->viewer->removeCurrentWindow();
        }
    }

    public function render(): void
    {
        $inventory = $this->menu->getInventory();
        $inventory->clearAll();

        foreach (range(0, 53) as $slot) {
            $inventory->setItem($slot, $this->makeFiller(DyeColor::BLACK()));
        }

        foreach ($this->session->getOwnOfferSlots($this->viewer) as $slot) {
            $inventory->setItem($slot, VanillaItems::AIR());
        }
        foreach ($this->session->getOpponentOfferSlots($this->viewer) as $slot) {
            $inventory->setItem($slot, VanillaItems::AIR());
        }

        foreach ($this->session->getOwnOfferItems($this->viewer) as $i => $item) {
            $slot = $this->session->getOwnOfferSlots($this->viewer)[$i] ?? null;
            if ($slot !== null && $item instanceof Item && !$item->isNull()) {
                $inventory->setItem($slot, clone $item);
            }
        }
        foreach ($this->session->getOpponentOfferItems($this->viewer) as $i => $item) {
            $slot = $this->session->getOpponentOfferSlots($this->viewer)[$i] ?? null;
            if ($slot !== null && $item instanceof Item && !$item->isNull()) {
                $inventory->setItem($slot, clone $item);
            }
        }

        foreach (ItemFlipSession::DIVIDER_SLOTS as $slot) {
            $inventory->setItem($slot, $this->makeFiller(DyeColor::GRAY()));
        }

        $rules = VanillaItems::BOOK();
        $rules->setCustomName(C::colorize("&r&6Item Flip Guide"));
        $rules->setLore($this->buildGuideLore());
        $inventory->setItem(22, $rules);

        $inventory->setItem(49, $this->buildCenterMarker());
        $inventory->setItem($this->session->getReadySlot(), $this->buildControlButton());

        if ($this->session->isRolling() || $this->session->isFinished()) {
            $this->renderRollRow();
            return;
        }

        foreach (ItemFlipSession::COLOR_SELECTOR_SLOTS as $i => $slot) {
            $colorKey = $this->choiceOrder[$i] ?? null;
            if ($colorKey === null) {
                continue;
            }
            $inventory->setItem($slot, $this->buildColorSelector($colorKey));
        }
    }

    public function renderRollRow(): void
    {
        $inventory = $this->menu->getInventory();
        foreach (ItemFlipSession::ROLL_SLOTS as $slot) {
            $inventory->setItem($slot, $this->session->getRollItem($slot));
        }
    }

    private function buildControlButton(): Item
    {
        $item = VanillaBlocks::WOOL()->setColor($this->resolveReadyColor())->asItem();
        $myItems = $this->countItems($this->session->getOwnOfferItems($this->viewer));
        $theirItems = $this->countItems($this->session->getOpponentOfferItems($this->viewer));
        $myChoice = $this->session->getChosenColor($this->viewer);
        $oppChoice = $this->session->getPartnerChosenColor($this->viewer);

        if ($this->session->isCountingDown()) {
            $item->setCustomName(C::colorize("&r&b&lRolling in " . $this->session->getCountdownLeft()));
            $item->setLore([
                C::colorize("&r&7Both players are locked in."),
                C::colorize("&r&7Your items: &f" . $myItems . "   &7Their items: &f" . $theirItems),
                C::colorize("&r&7Your color: &f" . $this->formatChoiceLabel($myChoice)),
                C::colorize("&r&7Their color: &f" . $this->formatChoiceLabel($oppChoice)),
            ]);
            return $item;
        }

        if ($this->session->isViewerReady($this->viewer)) {
            $item->setCustomName(C::colorize("&r&a&lReady"));
            $item->setLore([
                C::colorize("&r&7Click again to unready."),
                C::colorize("&r&7Waiting on &f" . $this->opponent->getName()),
                C::colorize("&r&7Your color: &f" . $this->formatChoiceLabel($myChoice)),
                C::colorize("&r&7Your items: &f" . $myItems),
            ]);
            return $item;
        }

        if ($this->session->getReadyCount() === 1) {
            $item->setCustomName(C::colorize("&r&6&lOne Player Ready"));
            $item->setLore([
                C::colorize("&r&7Click to lock in your side."),
                C::colorize("&r&7Your color: &f" . $this->formatChoiceLabel($myChoice)),
                C::colorize("&r&7Your items: &f" . $myItems . "   &7Their items: &f" . $theirItems),
            ]);
            return $item;
        }

        $item->setCustomName(C::colorize("&r&c&lClick To Ready"));
        $item->setLore([
            C::colorize("&r&7Pick a color and add your items."),
            C::colorize("&r&7Then click here to lock in."),
            C::colorize("&r&7Your color: &f" . $this->formatChoiceLabel($myChoice)),
            C::colorize("&r&7Your items: &f" . $myItems . "   &7Their items: &f" . $theirItems),
        ]);
        return $item;
    }

    /**
     * @return list<string>
     */
    private function buildGuideLore(): array
    {
        $myChoice = $this->session->getChosenColor($this->viewer);
        $oppChoice = $this->session->getPartnerChosenColor($this->viewer);

        return [
            C::colorize("&r&7Opponent: &f" . $this->opponent->getName()),
            C::colorize("&r&7Cost: &f1 credit each"),
            C::colorize("&r&7Left side: &f" . $this->session->getPlayerA()->getName()),
            C::colorize("&r&7Right side: &f" . $this->session->getPlayerB()->getName()),
            C::colorize("&r&7Pick different colors, then ready."),
            C::colorize("&r&7Winner is the color landing on slot 49."),
            C::colorize("&r&7Your color: &f" . $this->formatChoiceLabel($myChoice)),
            C::colorize("&r&7Their color: &f" . $this->formatChoiceLabel($oppChoice)),
        ];
    }

    private function buildCenterMarker(): Item
    {
        $item = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::WHITE())->asItem();
        $item->setCustomName(C::colorize("&r&fWinning Slot"));
        $item->setLore([
            C::colorize("&r&7The final wool here wins."),
        ]);
        return $item;
    }

    private function buildColorSelector(string $colorKey): Item
    {
        $choice = ItemFlipSession::CHOICES[$colorKey];
        $item = VanillaBlocks::WOOL()->setColor($this->toDyeColor($colorKey))->asItem();
        $owner = $this->session->getSelectorOwnerName($colorKey);

        $name = "&r" . $choice["code"] . "&l" . $choice["label"];
        if ($owner !== null) {
            $name .= "&r&7 (" . $owner . ")";
        }
        $item->setCustomName(C::colorize($name));
        $item->setLore([
            C::colorize($owner === null ? "&r&7Tap to choose this color." : "&r&7Chosen by &f" . $owner),
        ]);

        if ($owner !== null) {
            $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 1));
        }

        return $item;
    }

    private function resolveReadyColor(): DyeColor
    {
        return match (true) {
            $this->session->isCountingDown() => DyeColor::LIME(),
            $this->session->isViewerReady($this->viewer) => DyeColor::LIME(),
            $this->session->getReadyCount() === 1 => DyeColor::ORANGE(),
            default => DyeColor::RED(),
        };
    }

    private function makeFiller(DyeColor $color): Item
    {
        return VanillaBlocks::STAINED_GLASS_PANE()->setColor($color)->asItem()->setCustomName(C::colorize("&r "));
    }

    private function formatChoiceLabel(?string $colorKey): string
    {
        if ($colorKey === null) {
            return "None";
        }

        return ItemFlipSession::CHOICES[$colorKey]["label"] ?? $colorKey;
    }

    /**
     * @param list<Item|null> $items
     */
    private function countItems(array $items): int
    {
        return count(array_filter($items, static fn($item): bool => $item instanceof Item && !$item->isNull()));
    }

    private function toDyeColor(string $colorKey): DyeColor
    {
        return match ($colorKey) {
            "red" => DyeColor::RED(),
            "orange" => DyeColor::ORANGE(),
            "yellow" => DyeColor::YELLOW(),
            "lime" => DyeColor::LIME(),
            "cyan" => DyeColor::CYAN(),
            "light_blue" => DyeColor::LIGHT_BLUE(),
            "purple" => DyeColor::PURPLE(),
            "pink" => DyeColor::PINK(),
            default => DyeColor::GRAY(),
        };
    }
}
