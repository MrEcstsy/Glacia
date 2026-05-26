<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\FactionGuardianEntity;
use ecstsy\Glacia\server\factions\guardian\FactionGuardianManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerCraftingInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;

final class FactionGuardianListener implements Listener
{
    public function onInteract(PlayerInteractEvent $event): void
    {
        if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            return;
        }

        $block = $event->getBlock();
        $player = $event->getPlayer();
        $item = $event->getItem();
        if (!FactionGuardianManager::isGuardianEgg($item)) {
            return;
        }

        $event->cancel();
        $player->getInventory()->setItemInHand(\pocketmine\item\VanillaItems::AIR());
        $player->sendMessage(C::colorize("&r&eThis Chunk Guardian egg was disabled and merged into your faction's Core Chunk system."));
    }

    public function onDrop(PlayerDropItemEvent $event): void
    {
        if (!FactionGuardianManager::isGuardianEgg($event->getItem())) {
            return;
        }

        $event->cancel();
        $event->getPlayer()->getInventory()->setItemInHand(\pocketmine\item\VanillaItems::AIR());
        $event->getPlayer()->sendMessage(C::colorize("&r&eThat Chunk Guardian egg was disabled and has been removed. &7Guardians now come from your Core Chunk."));
    }

    /**
     * @priority HIGHEST
     */
    public function onTransaction(InventoryTransactionEvent $event): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $source = $event->getTransaction()->getSource();
        if (!$source instanceof Player) {
            return;
        }

        foreach ($event->getTransaction()->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }
            if (!FactionGuardianManager::isGuardianEgg($action->getSourceItem()) && !FactionGuardianManager::isGuardianEgg($action->getTargetItem())) {
                continue;
            }
            if (self::isAllowedLeaderInventory($action->getInventory(), $source)) {
                continue;
            }

            $event->cancel();
            $source->sendTip(C::colorize("&r&eChunk Guardian eggs are disabled. &7They now come from your Core Chunk."));
            PlayerUtils::playSound($source, "note.bass");
            return;
        }
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        FactionGuardianManager::cleanPlayerGuardianEggs($event->getPlayer());
    }

    public function onDamage(EntityDamageEvent $event): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof FactionGuardianEntity) {
            return;
        }

        $event->cancel();
        if (!$event instanceof EntityDamageByEntityEvent) {
            return;
        }

        $damager = $event->getDamager();
        if ($damager instanceof Player) {
            FactionGuardianManager::handleGuardianHit($damager, $entity);
        }
    }

    private static function isAllowedLeaderInventory(\pocketmine\inventory\Inventory $inventory, Player $player): bool
    {
        if ($inventory instanceof PlayerInventory || $inventory instanceof PlayerCursorInventory || $inventory instanceof PlayerOffHandInventory || $inventory instanceof PlayerCraftingInventory) {
            return $inventory->getHolder() === $player;
        }
        if ($inventory instanceof ArmorInventory) {
            return false;
        }
        return false;
    }
}
