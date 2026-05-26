<?php

declare(strict_types=1);

namespace ecstsy\Glacia\managers;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;

final class BragTracker
{
    /**
     * @var array<string, array{
     *     hotbar: Item[],
     *     inventory: Item[],
     *     helmet: Item,
     *     chestplate: Item,
     *     leggings: Item,
     *     boots: Item,
     *     offhand: Item,
     *     level: int
     * }>
     */
    private static array $snapshots = [];

    public static function setLastInventoryBrag(Player $player): void
    {
        $key = strtolower($player->getName());
        $inventory = $player->getInventory()->getContents(false);

        $hotbar = array_map(
            static fn(int $slot): Item => $inventory[$slot] ?? VanillaItems::AIR(),
            range(0, 8)
        );

        $mainInventory = [];
        foreach (range(9, 35) as $slot) {
            $mainInventory[] = $inventory[$slot] ?? VanillaItems::AIR();
        }

        self::$snapshots[$key] = [
            "hotbar" => $hotbar,
            "inventory" => $mainInventory,
            "helmet" => clone $player->getArmorInventory()->getHelmet(),
            "chestplate" => clone $player->getArmorInventory()->getChestplate(),
            "leggings" => clone $player->getArmorInventory()->getLeggings(),
            "boots" => clone $player->getArmorInventory()->getBoots(),
            "offhand" => clone $player->getOffHandInventory()->getItem(0),
            "level" => $player->getXpManager()->getXpLevel(),
        ];
    }

    public static function hasLastInventoryBrag(string $name): bool
    {
        return isset(self::$snapshots[strtolower($name)]);
    }

    /**
     * @return array{
     *     hotbar: Item[],
     *     inventory: Item[],
     *     helmet: Item,
     *     chestplate: Item,
     *     leggings: Item,
     *     boots: Item,
     *     offhand: Item,
     *     level: int
     * }|null
     */
    public static function getLastInventoryBrag(string $name): ?array
    {
        return self::$snapshots[strtolower($name)] ?? null;
    }

    public static function clear(string $name): void
    {
        unset(self::$snapshots[strtolower($name)]);
    }
}
