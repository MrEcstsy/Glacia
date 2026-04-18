<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\slotbot;

use ecstsy\Glacia\server\slotbot\SlotbotRewardRepository;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SlotbotLootTableSelectorScreen extends BaseScreen {

    private const TAG_NORMAL = "glacia_slotbot_normal_loot";
    private const TAG_JACKPOT = "glacia_slotbot_selector_jackpot_loot";
    private const TAG_BACK = "glacia_slotbot_selector_back";

    private InvMenu $menu;

    public function __construct(private Player $player)
    {
        $this->menu = CustomSizedInvMenu::create(18);
        $this->menu->setName(C::colorize("&r&l&dSlotbot &8> &r&7Loot Tables"));

        $inventory = $this->menu->getInventory();
        $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY())->asItem()->setCustomName(" ");
        foreach (range(0, 17) as $slot) {
            $inventory->setItem($slot, clone $pane);
        }

        $inventory->setItem(3, $this->createItem(VanillaBlocks::BEACON()->asItem(), "&r&l&fNormal Loot Table", self::TAG_NORMAL, [
            "&r&7View the standard slotbot rewards."
        ]));
        $inventory->setItem(5, $this->createItem(VanillaItems::NETHER_STAR(), "&r&l&6Jackpot Loot Table", self::TAG_JACKPOT, [
            "&r&7View the rare jackpot rewards.",
            "&r&7Current chance: &f" . rtrim(rtrim(number_format(SlotbotRewardRepository::getJackpotChance(), 2), "0"), ".") . "%"
        ]));
        $inventory->setItem(13, $this->createItem(StringToItemParser::getInstance()->parse("chest_minecart") ?? VanillaItems::CHEST_MINECART(), "&r&l&cBack", self::TAG_BACK, [
            "&r&7Click to return to the Slotbot."
        ]));

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
        $tag = $item->getNamedTag();

        if ($tag->getTag(self::TAG_NORMAL) !== null) {
            PlayerUtils::playSound($player, "random.click");
            (new SlotbotLootTableScreen(
                $player,
                "&r&l&dSlotbot &8> &r&7Loot Table",
                SlotbotRewardRepository::getRewards(),
                "normal"
            ))->display($player);
            return false;
        }

        if ($tag->getTag(self::TAG_JACKPOT) !== null) {
            PlayerUtils::playSound($player, "random.click");
            (new SlotbotLootTableScreen(
                $player,
                "&r&l&dSlotbot &8> &r&6Jackpot Table",
                SlotbotRewardRepository::getRewards("jackpot-rewards"),
                "jackpot"
            ))->display($player);
            return false;
        }

        if ($tag->getTag(self::TAG_BACK) !== null) {
            PlayerUtils::playSound($player, "random.click");
            (new SlotbotMenuScreen($player))->display($player);
            return false;
        }

        return false;
    }

    private function createItem(Item $item, string $name, string $tagName, array $lore = []): Item
    {
        $item->setCustomName(C::colorize($name));
        if ($lore !== []) {
            $item->setLore(array_map(static fn(string $line): string => C::colorize($line), $lore));
        }

        $tag = $item->getNamedTag();
        $tag->setByte($tagName, 1);
        $item->setNamedTag($tag);
        return $item;
    }
}
