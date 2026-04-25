<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\hats;

use pocketmine\block\CarvedPumpkin;
use pocketmine\block\MobHead;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\player\Player;
use pocketmine\Server;

final class HatManager {

    public static function hasRenderedHat(Player $player): bool
    {
        return $player instanceof HatPlayer && $player->getRenderedHat() !== null;
    }

    public static function clearRenderedHat(Player $player): ?ItemBlock
    {
        if (!$player instanceof HatPlayer || $player->getRenderedHat() === null) {
            return null;
        }

        $item = $player->getRenderedHat()->getSourceItem();
        $player->getRenderedHat()->removeFrom($player, ...Server::getInstance()->getOnlinePlayers());
        $player->setRenderedHat(null);
        return $item;
    }

    public static function applyRenderedHat(Player $player, ItemBlock $block): bool
    {
        if (!$player instanceof HatPlayer) {
            return false;
        }

        $hat = $player->getRenderedHat();
        if ($hat === null) {
            $hat = new RenderedBlockHat($player, $block);
        } else {
            $hat->setBlock($block);
        }

        $player->setRenderedHat($hat);
        return true;
    }

    public static function syncHelmetChange(Player $player): void
    {
        if (!$player instanceof HatPlayer) {
            return;
        }

        if ($player->getArmorInventory()->getHelmet()->isNull()) {
            return;
        }

        self::clearRenderedHat($player);
    }

    public static function isRenderableBlockHat(Item $item): bool
    {
        if (!$item instanceof ItemBlock) {
            return false;
        }

        $block = $item->getBlock();
        if ($block instanceof CarvedPumpkin || $block instanceof MobHead) {
            return false;
        }

        return $block->isSolid() && !$block->isTransparent();
    }
}
