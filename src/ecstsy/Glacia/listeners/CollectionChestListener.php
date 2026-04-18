<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\trackers\TrackerManager;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\utils\screens\collectionchests\CollectionChestScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\Chest as ChestBlock;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\ItemBlock;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class CollectionChestListener implements Listener {

    public function onPlace(BlockPlaceEvent $event): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $item = $event->getItem();
        $glaciaTag = $item->getNamedTag()->getCompoundTag("Glacia");
        if (!$item instanceof ItemBlock || $glaciaTag === null || $glaciaTag->getString("glaciaItem", "") !== "collection_chest") {
            return;
        }

        $player = $event->getPlayer();
        $session = Loader::getPlayerManager()->getSession($player);
        $faction = $session?->getFaction();
        if ($faction === null) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cYou must be in a faction to place a Collection Chest."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            $position = $block->getPosition();
            $claim = ClaimManager::getInstance()->getClaimAtPosition($position);
            if ($claim === null || $claim->getFactionId() !== $faction->getFactionId()) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&cYou can only place a Collection Chest in your own claim."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!Loader::getCollectionChestManager()->registerChest($position, $faction->getFactionId())) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&cThere is already a Collection Chest in this chunk."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $player->sendMessage(C::colorize("&r&aCollection Chest deployed for this chunk."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }
    }

    public function onInteract(PlayerInteractEvent $event): void
    {
        $block = $event->getBlock();
        if (!$block instanceof ChestBlock) {
            return;
        }

        $position = $block->getPosition();
        $entry = Loader::getCollectionChestManager()->getChestAt($position);
        if ($entry === null) {
            return;
        }

        $player = $event->getPlayer();
        $factionId = Loader::getPlayerManager()->getSession($player)?->getFactionId();
        if ($factionId !== (string) $entry["faction_id"] && !$player->hasPermission("glacia.admin")) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cOnly members of the owning faction can use this Collection Chest."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $event->cancel();
        CollectionChestScreen::display($player, $position);
    }

    public function onBreak(BlockBreakEvent $event): void
    {
        $entry = Loader::getCollectionChestManager()->getChestAt($event->getBlock()->getPosition());
        if ($entry === null) {
            return;
        }

        $removed = Loader::getCollectionChestManager()->removeChest($event->getBlock()->getPosition());
        if ($removed === null) {
            return;
        }

        $player = $event->getPlayer();
        $event->setDrops($player->getGamemode() === GameMode::CREATIVE() ? [] : [GlaciaItemFactory::create("collection_chest")]);

        foreach (($removed["items"] ?? []) as $stored) {
            $template = \ecstsy\MartianUtilities\utils\ItemUtils::decodeItem((string) $stored["item_data"]);
            $remaining = (int) ($stored["count"] ?? 0);

            while ($remaining > 0) {
                $stack = clone $template;
                $stackSize = min($stack->getMaxStackSize(), $remaining);
                $stack->setCount($stackSize);
                foreach ($player->getInventory()->addItem($stack) as $overflow) {
                    $player->getWorld()->dropItem($player->getPosition(), $overflow);
                }
                $remaining -= $stackSize;
            }
        }

        $storedXp = (int) ($removed["xp"] ?? 0);
        if ($storedXp > 0) {
            $player->getWorld()->dropExperience($player->getPosition(), $storedXp);
        }

        $player->sendMessage(C::colorize("&r&eCollection Chest removed. Stored items and XP were returned."));
    }

    public function onEntityDeath(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof Living || $entity instanceof \ecstsy\Glacia\entity\CombatLoggerEntity) {
            return;
        }

        $last = $entity->getLastDamageCause();
        if ($last instanceof \pocketmine\event\entity\EntityDamageByEntityEvent) {
            $damager = $last->getDamager();
            if ($damager instanceof Projectile) {
                $damager = $damager->getOwningEntity();
            }

            if ($damager instanceof Player) {
                $hand = $damager->getInventory()->getItemInHand();
                $updatedHand = $entity instanceof Player
                    ? TrackerManager::addProgress($hand, TrackerManager::PLAYER_KILLS)
                    : TrackerManager::addProgress($hand, TrackerManager::MOB_KILLS);
                $updatedHand = TrackerManager::addProgress($updatedHand, TrackerManager::XP, max(0, $event->getXpDropAmount()));

                if (!$updatedHand->equalsExact($hand)) {
                    $damager->getInventory()->setItemInHand($updatedHand);
                }
            }
        }

        if ($entity instanceof Player) {
            return;
        }

        if (!Loader::getCollectionChestManager()->addDropsToChunk($entity->getPosition(), $event->getDrops(), $event->getXpDropAmount())) {
            return;
        }

        $event->setDrops([]);
        $event->setXpDropAmount(0);
    }
}
