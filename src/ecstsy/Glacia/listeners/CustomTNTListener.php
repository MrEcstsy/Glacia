<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\GlaciaExplosiveCreeper;
use ecstsy\Glacia\entity\GlaciaPrimedTNT;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\obsidian\ObsidianDurabilityManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\TNT;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\FireCharge;
use pocketmine\item\FlintSteel;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\player\GameMode;
use pocketmine\utils\TextFormat as C;

final class CustomTNTListener implements Listener {

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $item = $event->getItem();
        $glaciaItem = $this->getGlaciaItemId($item);

        if (!CustomTNTIds::isCustomTNT($glaciaItem)) {
            return;
        }

        $blockData = Loader::getBlockData();

        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            if (!$block instanceof TNT) {
                continue;
            }

            $blockData->set($block, [
                "type" => $glaciaItem
            ]);
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $block = $event->getBlock();

        if (ObsidianDurabilityManager::isTrackedObsidian($block)) {
            ObsidianDurabilityManager::clear($block);
        }

        if (!$block instanceof TNT) {
            return;
        }

        $blockData = Loader::getBlockData();
        $data = $blockData->get($block);

        if (!is_array($data)) {
            return;
        }

        $type = (string) ($data["type"] ?? "");
        if (!CustomTNTIds::isCustomTNT($type)) {
            return;
        }

        $blockData->remove($block);

        $event->setDrops([
            GlaciaItemFactory::tnt($type)
        ]);
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $item = $event->getItem();

        if (
            $item->getTypeId() === ItemTypeIds::POTATO &&
            ObsidianDurabilityManager::isTrackedObsidian($block) &&
            $event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK
        ) {
            $hp = ObsidianDurabilityManager::getHp($block);
            $max = ObsidianDurabilityManager::getMaxHp();
            $player->sendActionBarMessage(C::colorize("&r&5Obsidian Durability &8| &r&f{$hp}&7/&f{$max}"));
            PlayerUtils::playSound($player, "random.click");
            return;
        }

        if (!$block instanceof TNT) {
            return;
        }

        if (!$item instanceof FlintSteel && !$item instanceof FireCharge) {
            return;
        }

        $blockData = Loader::getBlockData();
        $data = $blockData->get($block);

        if (!is_array($data)) {
            return;
        }

        $type = (string) ($data["type"] ?? "");
        if (!CustomTNTIds::isCustomTNT($type)) {
            return;
        }

        $event->cancel();

        if (Loader::getGraceInstance()->isEnabled()) {
            $player->sendMessage(C::colorize("&r&cYou cannot use custom TNT during grace."));
            return;
        }

        $blockData->remove($block);

        $world = $block->getPosition()->getWorld();
        $pos = $block->getPosition();

        $world->setBlock($pos, VanillaBlocks::AIR());

        $tnt = new GlaciaPrimedTNT(new Location(
            $pos->getX() + 0.5,
            $pos->getY(),
            $pos->getZ() + 0.5,
            $world,
            0.0,
            0.0
        ));
        $tnt->setTNTType($type);
        $tnt->spawnToAll();

        PlayerUtils::playSound($player, "random.fuse");

        if ($player->getGamemode() !== GameMode::CREATIVE()) {
            if ($item instanceof FlintSteel) {
                $item->applyDamage(1);
                $player->getInventory()->setItemInHand($item);
            } elseif ($item instanceof FireCharge) {
                $item->pop();
                $player->getInventory()->setItemInHand($item);
            }
        }
    }

    private function getGlaciaItemId(Item $item): string {
        $tag = $item->getNamedTag();

        $glacia = $tag->getCompoundTag("Glacia");
        if ($glacia !== null) {
            return $glacia->getString("glaciaItem", "");
        }

        return $tag->getString("glaciaItem", "");
    }

    public function onInteractEntity(PlayerEntityInteractEvent $event): void {
        $player = $event->getPlayer();
        $entity = $event->getEntity();
        $item = $player->getInventory()->getItemInHand();

        if (!$entity instanceof GlaciaExplosiveCreeper) {
            return;
        }

        if (!$item instanceof FlintSteel && !$item instanceof FireCharge) {
            return;
        }

        $event->cancel();

        $entity->ignite();
        PlayerUtils::playSound($player, "random.fuse");

        if ($player->getGamemode() !== GameMode::CREATIVE()) {
            if ($item instanceof FlintSteel) {
                $item->applyDamage(1);
                $player->getInventory()->setItemInHand($item);
            } elseif ($item instanceof FireCharge) {
                $item->pop();
                $player->getInventory()->setItemInHand($item);
            }
        }
    }

    public function onDeath(EntityDeathEvent $event): void {
        $entity = $event->getEntity();

        if (!$entity instanceof GlaciaExplosiveCreeper) {
            return;
        }

        if (!$entity->shouldDropCegg()) {
            return;
        }

        $identifier = $entity->getCeggIdentifier();
        if ($identifier === null) {
            return;
        }

        $event->setDrops([
            GlaciaItemFactory::cegg($identifier)
        ]);
    }

    public function onExplode(EntityExplodeEvent $event): void
    {
        $entity = $event->getEntity();
        if (
            !$entity instanceof GlaciaPrimedTNT &&
            !$entity instanceof GlaciaExplosiveCreeper
        ) {
            return;
        }

        $updated = [];
        foreach ($event->getBlockList() as $block) {
            if (!ObsidianDurabilityManager::isTrackedObsidian($block)) {
                $updated[] = $block;
                continue;
            }

            $remaining = ObsidianDurabilityManager::damage($block);
            if ($remaining > 0) {
                continue;
            }

            $updated[] = $block;
        }

        $event->setBlockList($updated);
    }
}
