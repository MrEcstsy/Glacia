<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\Glacia\utils\screens\quests\QuestsScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class QuestListener implements Listener {

    public function onPlayerItemUse(PlayerItemUseEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $nbt = $item->getNamedTag();
        $glacia = $nbt?->getCompoundTag("Glacia");
        if (($glacia?->getString("glaciaItem", "") ?? "") === "quest_reroll") {
            $event->cancel();
            if (!Loader::getQuestBoardManager()->isEnabled()) {
                $player->sendMessage(C::colorize("&r&cQuests are currently disabled."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }
            QuestsScreen::displayRerollMenu($player);
            PlayerUtils::playSound($player, "random.click");
            return;
        }

        $tier = strtolower($nbt?->getCompoundTag("Glacia")?->getString("questTier", $nbt?->getString("glacia_quest_tier", "")) ?? "");

        if ($tier === "") {
            return;
        }

        $event->cancel();
        if (!Loader::getQuestBoardManager()->useSigil($player, $tier)) {
            return;
        }

        $item->pop();
        $player->getInventory()->setItemInHand($item);
    }

    public function onEntityDeath(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();
        $cause = $entity->getLastDamageCause();

        if (!$cause instanceof EntityDamageByEntityEvent) {
            return;
        }

        $damager = $cause->getDamager();
        if ($damager instanceof Player) {
            QuestInstance::addProgress($damager, "kill", $entity, 1);
            if (!$entity instanceof \pocketmine\player\Player) {
                Loader::getQuestBoardManager()->recordProgress($damager, "mobs_killed", 1);
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void
    {
        QuestInstance::addProgress($event->getPlayer(), "mine", $event->getBlock()->asItem(), 1);
        if ($event->getBlock()->asItem()->getTypeId() === VanillaBlocks::MELON()->asItem()->getTypeId()) {
            Loader::getQuestBoardManager()->recordProgress($event->getPlayer(), "melons_mined", 1);
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        QuestInstance::addProgress($event->getPlayer(), "place", $event->getItem(), 1);
    }
}
