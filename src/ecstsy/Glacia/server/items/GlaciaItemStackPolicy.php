<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items;

use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * Rules for "low-key" non-stackable Glacia items in player storage (excludes UIs: chest menus use their own
 * inventory holders, not these cleanups). Pet skull items cannot be worn as a helmet; split stacks into singles.
 */
final class GlaciaItemStackPolicy
{
    /** @var list<string> */
    private const PET_GI = [
        "puritywisp",
        "galefox",
        "shatterfang",
        "hearthbloom",
        "ironcladwyrm",
        "aegisram",
        "riftviper",
        "sunscorchjackal",
        "runesageowl",
        "stormcorebehemoth",
        "veilmoth",
        "blastforgehound",
        "gildedmoth",
        "rafflefinch",
        "oathbondbeetle",
        "murmurloft",
        "patchworksprite",
        "renderscalewyrm",
        "skycourierdrake",
        "inkwellfinch",
        "terracrestjackal",
        "soulsurge",
        "ruinspiremoth",
        "bellforgecustodian",
        "arcbindercodex",
        "crownspirekestrel",
        "shadebrokercrow",
    ];

    /** @var list<string> Trinket category + soul compass: stacking causes bad NBT / usage state. */
    private const NON_STACKABLE_GI = [
        "soul_conduit",
        "efficiency_conduit",
        "overlord_conduit",
        "soul_gem",
        "efficiency_gem",
        "overlord_gem",
        "soul_compass",
        "frostwake_trident",
        "stormflare_torch",
        "glacial_blessing",
        "tempest_gateway",
        "faction_guardian_egg",
        "random_magicdust",
    ];

    public static function isPetItem(Item $item): bool
    {
        $g = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($g === null) {
            return false;
        }

        return in_array(GlaciaItemFactory::resolvePetAliasKey($g->getString("glaciaItem", "")), self::PET_GI, true);
    }

    /**
     * Items that should exist as count 1 per slot in player/ender/cursor/off-hand (not in arbitrary menu inventories).
     */
    public static function shouldUnstackInPlayerStorage(Item $item): bool
    {
        $g = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($g === null) {
            return false;
        }
        $t = $g->getString("glaciaItem", "");
        if ($t === "lootbox" || $t === "ethereal_crate") {
            return true;
        }
        if (str_starts_with($t, "ethereal_") && str_ends_with($t, "_chest")) {
            return true;
        }
        if (in_array($t, self::NON_STACKABLE_GI, true)) {
            return true;
        }

        return false;
    }

    public static function normalizePlayerStorages(Player $player): void
    {
        self::unstackInventory($player->getInventory(), $player);
        self::unstackInventory($player->getArmorInventory(), $player);
        self::unstackInventory($player->getOffHandInventory(), $player);
        self::unstackInventory($player->getEnderInventory(), $player);
    }

    private static function unstackInventory(Inventory $inv, Player $player): void
    {
        $size = $inv->getSize();
        for ($slot = 0; $slot < $size; $slot++) {
            $item = $inv->getItem($slot);
            if ($item->isNull() || $item->getCount() <= 1) {
                continue;
            }
            if (!self::shouldUnstackInPlayerStorage($item)) {
                continue;
            }
            $total = $item->getCount();
            $one = clone $item;
            $one->setCount(1);
            $inv->setItem($slot, $one);
            for ($i = 1; $i < $total; $i++) {
                $piece = clone $one;
                $empty = $inv->firstEmpty();
                if ($empty === -1) {
                    $player->getWorld()->dropItem($player->getPosition()->add(0, 0.5, 0), $piece);
                    continue;
                }
                $inv->setItem($empty, $piece);
            }
        }
    }
}
