<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\shop;

use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\PotionType;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ShopScreen extends BaseScreen {

    public InvMenu $invMenu;

    public function __construct()
    {
        $this->invMenu = CustomSizedInvMenu::create(18);
        $this->invMenu->setName(C::colorize("&r&l&8Shop Categories"));

        $potionCategory = VanillaItems::POTION()->setType(PotionType::AWKWARD())->setCustomName(C::colorize("&r&l&ePotion Shop"))->setLore([
            C::colorize("&r&7Click to view this category.")
        ]);
        $raidCategory = VanillaItems::REDSTONE_DUST()->setCustomName(C::colorize("&r&l&eRaid Shop"))->setLore([
            C::colorize("&r&7Click to view this category.")
        ]);
        $spawnerCategory = VanillaBlocks::MONSTER_SPAWNER()->asItem()->setCustomName(C::colorize("&r&l&eSpawners"))->setLore([
            C::colorize("&r&7Click to view this category.")
        ]);
        $buildingBlocks = VanillaBlocks::STONE_BRICKS()->asItem()->setCustomName(C::colorize("&r&l&eBuilding Blocks"))->setLore([
            C::colorize("&r&7Click to view this category.")
        ]);
        $oreGemCategory = VanillaItems::EMERALD()->setCustomName(C::colorize("&r&l&eOre and Gems"))->setLore([
            C::colorize("&r&7Click to view this category.")
        ]);
        $foodFarmingCategory = VanillaItems::STEAK()->setCustomName(C::colorize("&r&l&eFood and Farming"))->setLore([
            C::colorize("&r&7Click to view this category.")
        ]);
        $mobDropsCategory = VanillaItems::BLAZE_ROD()->setCustomName(C::colorize("&r&l&eMob Drops"))->setLore([
            C::colorize("&r&7Click to view this category.")
        ]);
        $specialtyCategory = StringToItemParser::getInstance()->parse("ender_eye")->setCustomName(C::colorize("&r&l&eSpecialty Items"))->setLore([
            C::colorize("&r&7Click to view this category.")
        ]);
        $brewingCategory = VanillaBlocks::BREWING_STAND()->asItem()->setCustomName(C::colorize("&r&l&eBrewing"))->setLore([
            C::colorize("&r&7Click to view this category.")
        ]);
        $woolCategory = VanillaBlocks::WOOL()->asItem()->setCustomName(C::colorize("&r&l&eWool"))->setLore([
            C::colorize("&r&7Click to view this category.")
        ]);
        $glassCategory = VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::WHITE())->asItem()->setCustomName(C::colorize("&r&l&eGlass"))->setLore([
            C::colorize("&r&7Click to view this category.")
        ]);
        $baseGrindCategory = VanillaBlocks::OBSIDIAN()->asItem()->setCustomName(C::colorize("&r&l&eBase Grind"))->setLore([
            C::colorize("&r&7Click to view this category.") 
        ]);

        $inv = $this->invMenu->getInventory();

        $inv->setItem(0, $potionCategory);
        $inv->setItem(1, $raidCategory);
        $inv->setItem(2, $spawnerCategory);
        $inv->setItem(3, $buildingBlocks);
        $inv->setItem(4, $oreGemCategory);
        $inv->setItem(5, $foodFarmingCategory);
        $inv->setItem(6, $mobDropsCategory);
        $inv->setItem(7, $specialtyCategory);
        $inv->setItem(8, $brewingCategory);
        $inv->setItem(12, $woolCategory);

        $inv->setItem(13, $glassCategory);
        $inv->setItem(14, $baseGrindCategory);

        $this->invMenu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            $categories = [
                0 => "potions",
                1 => "raid",
                2 => "spawners",
                3 => "blocks",
                4 => "ores",
                5 => "food",
                6 => "drops",
                7 => "special",
                8 => "brewing",
                12 => "wool",
                13 => "glass",
                14 => "base"
            ];

            if (!isset($categories[$slot])) return;

            (new ShopCategoryScreen($categories[$slot]))->display($player);
        }));
    }

    public function getMenu(): ?InvMenu
    {
        return $this->invMenu;
    }

    public static function display(Player $player): void
    {
        (new self())->getMenu()->send($player);
    }
}