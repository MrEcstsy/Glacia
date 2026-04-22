<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\slotbot;

use ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SlotbotLootTableScreen extends BaseScreen {

    private const TAG_BACK = "glacia_slotbot_loot_back";

    private InvMenu $menu;
    private string $pool;

    /**
     * @param Item[] $rewards
     */
    public function __construct(private Player $player, string $title, array $rewards, string $pool)
    {
        $this->pool = $pool;
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize($title));

        $inventory = $this->menu->getInventory();
        $slot = 0;
        foreach ($rewards as $reward) {
            if ($slot === 53) {
                $slot++;
            }

            if ($slot >= $inventory->getSize()) {
                break;
            }

            $inventory->setItem($slot, clone $reward);
            $slot++;
        }

        $back = StringToItemParser::getInstance()->parse("chest_minecart")
            ->setCustomName(C::colorize("&r&l&cBack"))
            ->setLore([C::colorize("&r&7Click to return to loot table selection.")]);
        $tag = $back->getNamedTag();
        $tag->setByte(self::TAG_BACK, 1);
        $back->setNamedTag($tag);
        $this->menu->getInventory()->setItem(53, $back);

        $this->menu->setListener(InvMenu::readonly(
            fn(DeterministicInvMenuTransaction $transaction): bool => $this->handle($transaction)
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

    private function handle(DeterministicInvMenuTransaction $transaction): bool
    {
        $player = $transaction->getPlayer();
        $item = $transaction->getItemClicked();

        if ($item->getNamedTag()->getTag(self::TAG_BACK) !== null) {
            PlayerUtils::playSound($player, "random.click");
            (new SlotbotLootTableSelectorScreen($player))->display($player);
        }

        return false;
    }
}
