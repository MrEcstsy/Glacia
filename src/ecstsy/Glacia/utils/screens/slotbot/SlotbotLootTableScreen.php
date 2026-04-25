<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\slotbot;

use ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SlotbotLootTableScreen extends BaseScreen {

    private const TAG_BACK = "glacia_slotbot_loot_back";
    private const ITEMS_PER_PAGE = 45;

    private InvMenu $menu;
    private int $page = 1;
    /** @var Item[] */
    private array $rewards;

    /**
     * @param Item[] $rewards
     */
    public function __construct(private Player $player, string $title, array $rewards, string $pool)
    {
        $this->rewards = array_values($rewards);
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize($title));

        $this->menu->setListener(InvMenu::readonly(
            fn(DeterministicInvMenuTransaction $transaction): bool => $this->handle($transaction)
        ));
        $this->build();
    }

    public function display(Player $player): void
    {
        $this->menu->send($player);
    }

    public function getMenu(): ?InvMenu
    {
        return $this->menu;
    }

    private function handle(DeterministicInvMenuTransaction $transaction): bool
    {
        $player = $transaction->getPlayer();
        $slot = $transaction->getAction()->getSlot();

        if ($slot === 45 && $this->page > 1) {
            PlayerUtils::playSound($player, "random.click");
            $this->page--;
            $this->build();
            return false;
        }

        if ($slot === 53 && $this->page < $this->getTotalPages()) {
            PlayerUtils::playSound($player, "random.click");
            $this->page++;
            $this->build();
            return false;
        }

        $item = $transaction->getItemClicked();
        if ($slot === 49 || $item->getNamedTag()->getTag(self::TAG_BACK) !== null) {
            PlayerUtils::playSound($player, "random.click");
            (new SlotbotLootTableSelectorScreen($player))->display($player);
        }

        return false;
    }

    /**
     */
    private function build(): void
    {
        $inventory = $this->menu->getInventory();
        $inventory->clearAll();

        $slot = 0;
        foreach (array_slice($this->rewards, ($this->page - 1) * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE) as $reward) {
            $inventory->setItem($slot, clone $reward);
            $slot++;
        }

        $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" ");
        for ($controlSlot = 45; $controlSlot <= 53; $controlSlot++) {
            $inventory->setItem($controlSlot, clone $pane);
        }

        $hasMultiplePages = count($this->rewards) > self::ITEMS_PER_PAGE;

        if ($this->page > 1) {
            $previous = VanillaItems::PAPER();
            $previous->setCustomName(C::colorize("&r&ePrevious Page"));
            $inventory->setItem(45, $previous);
        }

        if ($hasMultiplePages) {
            $indicator = VanillaItems::BOOK();
            $indicator->setCustomName(C::colorize("&r&bPage &f{$this->page}&7/&f" . $this->getTotalPages()));
            $indicator->setLore([
                C::colorize("&r&7Browse the loot table without"),
                C::colorize("&r&7reopening the menu.")
            ]);
            $inventory->setItem(48, $indicator);
        }

        $back = StringToItemParser::getInstance()->parse("chest_minecart")
            ?? VanillaItems::CHEST_MINECART();
        $back->setCustomName(C::colorize("&r&l&cBack"));
        $back->setLore([C::colorize("&r&7Click to return to loot table selection.")]);
        $tag = $back->getNamedTag();
        $tag->setByte(self::TAG_BACK, 1);
        $back->setNamedTag($tag);
        $inventory->setItem(49, $back);

        if ($this->page < $this->getTotalPages()) {
            $next = VanillaItems::PAPER();
            $next->setCustomName(C::colorize("&r&eNext Page"));
            $inventory->setItem(53, $next);
        }
    }

    private function getTotalPages(): int
    {
        return max(1, (int) ceil(count($this->rewards) / self::ITEMS_PER_PAGE));
    }
}
