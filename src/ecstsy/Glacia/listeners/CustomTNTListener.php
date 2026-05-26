<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\GlaciaExplosiveCreeper;
use ecstsy\Glacia\entity\GlaciaPrimedTNT;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\factions\claims\CoreChunkRaidManager;
use ecstsy\Glacia\server\cannon\CannonRulesConfig;
use ecstsy\Glacia\server\obsidian\EnchantmentTableDurabilityManager;
use ecstsy\Glacia\server\obsidian\EnchantmentTableExplosionConfig;
use ecstsy\Glacia\server\obsidian\ObsidianDurabilityManager;
use ecstsy\Glacia\server\obsidian\ObsidianExplosionConfig;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\TNT;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\object\PrimedTNT;
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
use pocketmine\world\World;

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

        if (EnchantmentTableDurabilityManager::isTrackedTable($block)) {
            EnchantmentTableDurabilityManager::clear($block);
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
            $event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK
        ) {
            if (ObsidianDurabilityManager::isTrackedObsidian($block)) {
                $hp = ObsidianDurabilityManager::getHp($block);
                $max = ObsidianDurabilityManager::getMaxHp();
                $player->sendActionBarMessage(C::colorize("&r&5Obsidian Durability &8| &r&f{$hp}&7/&f{$max}"));
                PlayerUtils::playSound($player, "random.click");

                return;
            }
            if (EnchantmentTableDurabilityManager::isTrackedTable($block)) {
                $hp = EnchantmentTableDurabilityManager::getHp($block);
                $max = EnchantmentTableDurabilityManager::getMaxHp();
                $player->sendActionBarMessage(C::colorize("&r&5Enchantment Table Durability &8| &r&f{$hp}&7/&f{$max}"));
                PlayerUtils::playSound($player, "random.click");

                return;
            }
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
        $tnt->setRaidSourceFactionId(Loader::getPlayerManager()->getSession($player)?->getFactionId());
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

    public function onExplode(EntityExplodeEvent $event): void {
        $entity = $event->getEntity();
        if (!$entity instanceof PrimedTNT && !$entity instanceof GlaciaExplosiveCreeper) {
            return;
        }
        if ($event->isCancelled()) {
            return;
        }

        if (CannonRulesConfig::enabled() && CannonRulesConfig::suppressExplosionFullyAt($event->getPosition())) {
            return;
        }

        if ($entity instanceof GlaciaExplosiveCreeper && $this->isCeggBlockDamageProtectedRegion($event->getPosition())) {
            $event->setBlockList([]);
        }

        if ($entity instanceof GlaciaPrimedTNT) {
            CoreChunkRaidManager::handleTntExplosion($entity, $event->getPosition());
        } elseif ($entity instanceof GlaciaExplosiveCreeper) {
            $attackerFactionId = $entity->getRaidSourceFactionId();
            if ($attackerFactionId !== null) {
                CoreChunkRaidManager::handleFactionExplosionAt($attackerFactionId, $event->getPosition());
            }
        }

        if (ObsidianExplosionConfig::waterBlocksDamage()
            && GlaciaPrimedTNT::isEntityTouchingWater($entity)) {
            return;
        }

        $profile = $this->resolveObsidianExplosionProfile($entity);

        $fraction = ObsidianExplosionConfig::fractionalWearPerExplodeEvent($profile);

        $uniqObsidianByPos = [];

        foreach ($event->getBlockList() as $block) {
            if (!ObsidianDurabilityManager::isTrackedObsidian($block)) {
                continue;
            }
            $p = $block->getPosition();
            $uniqObsidianByPos[World::blockHash($p->getFloorX(), $p->getFloorY(), $p->getFloorZ())] = $block;
        }

        // Obsidian usually isn't part of vanilla explodeA lists — radial scan overlaps without double-wearing blocks.
        $center = $event->getPosition();
        $radius = $entity instanceof GlaciaPrimedTNT ? 4.0 : 3.0;
        $radiusInt = (int) ceil($radius);
        $world = $center->getWorld();

        $visitedCoords = [];

        for ($x = -$radiusInt; $x <= $radiusInt; $x++) {
            for ($y = -$radiusInt; $y <= $radiusInt; $y++) {
                for ($z = -$radiusInt; $z <= $radiusInt; $z++) {
                    $vx = ((int) floor($center->x)) + $x;
                    $vy = ((int) floor($center->y)) + $y;
                    $vz = ((int) floor($center->z)) + $z;

                    $ck = "{$vx}:{$vy}:{$vz}";
                    if (isset($visitedCoords[$ck])) {
                        continue;
                    }
                    $visitedCoords[$ck] = true;

                    $dx = $vx + 0.5 - $center->x;
                    $dy = $vy + 0.5 - $center->y;
                    $dz = $vz + 0.5 - $center->z;

                    if (($dx ** 2) + ($dy ** 2) + ($dz ** 2) > ($radius ** 2)) {
                        continue;
                    }

                    $block = $world->getBlockAt($vx, $vy, $vz);
                    if (!ObsidianDurabilityManager::isTrackedObsidian($block)) {
                        continue;
                    }

                    $hash = World::blockHash($vx, $vy, $vz);
                    $uniqObsidianByPos[$hash] = $block;
                }
            }
        }

        $breakHashes = [];
        foreach ($uniqObsidianByPos as $hash => $ob) {
            if (ObsidianDurabilityManager::applyExplosionWear($ob, $fraction)) {
                $breakHashes[$hash] = true;
            }
        }

        $outByHash = [];
        foreach ($event->getBlockList() as $block) {
            if (!ObsidianDurabilityManager::isTrackedObsidian($block)) {
                $h = World::blockHash(
                    $block->getPosition()->getFloorX(),
                    $block->getPosition()->getFloorY(),
                    $block->getPosition()->getFloorZ()
                );
                $outByHash[$h] = $block;
                continue;
            }

            $h = World::blockHash(
                $block->getPosition()->getFloorX(),
                $block->getPosition()->getFloorY(),
                $block->getPosition()->getFloorZ()
            );
            if (isset($breakHashes[$h])) {
                $outByHash[$h] = $block;
            }
        }

        foreach ($uniqObsidianByPos as $hash => $block) {
            if (!isset($breakHashes[$hash]) || isset($outByHash[$hash])) {
                continue;
            }
            $outByHash[$hash] = $block;
        }

        $event->setBlockList(array_values($outByHash));

        $this->applyEnchantmentTableExplosionWear($event, $entity, $profile);
    }

    private function applyEnchantmentTableExplosionWear(EntityExplodeEvent $event, Entity $entity, string $profile): void {
        $fraction = EnchantmentTableExplosionConfig::fractionalWearPerExplodeEvent($profile);

        $uniqByPos = [];

        foreach ($event->getBlockList() as $block) {
            if (!EnchantmentTableDurabilityManager::isTrackedTable($block)) {
                continue;
            }
            $p = $block->getPosition();
            $uniqByPos[World::blockHash($p->getFloorX(), $p->getFloorY(), $p->getFloorZ())] = $block;
        }

        $center = $event->getPosition();
        $radius = $entity instanceof GlaciaPrimedTNT ? 4.0 : 3.0;
        $radiusInt = (int) ceil($radius);
        $world = $center->getWorld();

        $visitedCoords = [];

        for ($x = -$radiusInt; $x <= $radiusInt; ++$x) {
            for ($y = -$radiusInt; $y <= $radiusInt; ++$y) {
                for ($z = -$radiusInt; $z <= $radiusInt; ++$z) {
                    $vx = ((int) floor($center->x)) + $x;
                    $vy = ((int) floor($center->y)) + $y;
                    $vz = ((int) floor($center->z)) + $z;

                    $ck = "{$vx}:{$vy}:{$vz}";
                    if (isset($visitedCoords[$ck])) {
                        continue;
                    }
                    $visitedCoords[$ck] = true;

                    $dx = $vx + 0.5 - $center->x;
                    $dy = $vy + 0.5 - $center->y;
                    $dz = $vz + 0.5 - $center->z;

                    if (($dx ** 2) + ($dy ** 2) + ($dz ** 2) > ($radius ** 2)) {
                        continue;
                    }

                    $block = $world->getBlockAt($vx, $vy, $vz);
                    if (!EnchantmentTableDurabilityManager::isTrackedTable($block)) {
                        continue;
                    }

                    $hash = World::blockHash($vx, $vy, $vz);
                    $uniqByPos[$hash] = $block;
                }
            }
        }

        $breakHashes = [];
        foreach ($uniqByPos as $hash => $tb) {
            if (EnchantmentTableDurabilityManager::applyExplosionWear($tb, $fraction)) {
                $breakHashes[$hash] = true;
            }
        }

        $outByHash = [];
        foreach ($event->getBlockList() as $block) {
            if (!EnchantmentTableDurabilityManager::isTrackedTable($block)) {
                $h = World::blockHash(
                    $block->getPosition()->getFloorX(),
                    $block->getPosition()->getFloorY(),
                    $block->getPosition()->getFloorZ()
                );
                $outByHash[$h] = $block;
                continue;
            }

            $h = World::blockHash(
                $block->getPosition()->getFloorX(),
                $block->getPosition()->getFloorY(),
                $block->getPosition()->getFloorZ()
            );
            if (isset($breakHashes[$h])) {
                $outByHash[$h] = $block;
            }
        }

        foreach ($uniqByPos as $hash => $block) {
            if (!isset($breakHashes[$hash]) || isset($outByHash[$hash])) {
                continue;
            }
            $outByHash[$hash] = $block;
        }

        $event->setBlockList(array_values($outByHash));
    }

    private function resolveObsidianExplosionProfile(Entity $entity): string {
        if ($entity instanceof GlaciaPrimedTNT) {
            $type = $entity->getTNTType();

            return ($type !== "" && CustomTNTIds::isCustomTNT($type))
                ? $type
                : CustomTNTIds::PROFILE_NORMAL;
        }

        if ($entity instanceof GlaciaExplosiveCreeper) {
            $type = $entity->getExplosiveType();

            return ($type !== "" && CustomTNTIds::isCustomTNT($type))
                ? $type
                : CustomTNTIds::PROFILE_NORMAL;
        }

        return CustomTNTIds::PROFILE_NORMAL;
    }

    private function isCeggBlockDamageProtectedRegion(\pocketmine\math\Vector3 $position): bool
    {
        foreach (Loader::getRegionManager()->getRegionsAt($position) as $region) {
            $name = strtolower($region->getName());
            if ($name === "spawn" || $name === "warzone" || str_starts_with($name, "warzone_")) {
                return true;
            }
        }

        return false;
    }
}
