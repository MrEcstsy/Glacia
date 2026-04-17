<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\mobs\Zombie;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\Glacia\server\quests\QuestManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\FireworkRocketTypeIdMap;
use pocketmine\data\bedrock\FireworkRocketTypeIds;
use pocketmine\entity\object\FireworkRocket;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\item\FireworkRocketType;
use pocketmine\player\Player;
use pocketmine\world\particle\LavaDripParticle;
use pocketmine\world\particle\LavaParticle;

final class QuestListener implements Listener {

    public function onPlayerItemUse(PlayerItemUseEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $nbt = $item->getNamedTag();

        if ($nbt === null || !$nbt->getTag("glacia_quest_tier")) return;

        $tier = $nbt->getString("glacia_quest_tier");
        $isComplete = $nbt->getByte("glacia_quest_complete", 0) === 1;

        if (!$isComplete) {
            $player->sendMessage("§cThis quest is not complete yet!");
            PlayerUtils::playSound($player, "block.lava.pop");
            $pos = $player->getPosition()->add(0, 1.5, 0);
            $player->getWorld()->addParticle($pos, new LavaParticle());
            return;
        }

        $tierData = QuestInstance::getTierData($tier);
        if ($tierData === null) {
            $player->sendMessage("§cInvalid quest tier.");
            return;
        }

        $rewards = $tierData['rewards'] ?? [];
        if (empty($rewards)) {
            $player->sendMessage("§cNo rewards configured for this tier.");
            return;
        }

        $reward = clone $rewards[array_rand($rewards)];
        $player->getInventory()->addItem($reward);

        $item->pop();
        $player->getInventory()->setItemInHand($item);

        $player->sendMessage("§aYou claimed a reward from the {$tierData['display']} tier!");
        PlayerUtils::playSound($player, "random.levelup");

        switch($tier) {
            case "dormant":
                $mainColors = [DyeColor::PURPLE(), DyeColor::MAGENTA()];
                $fadeColors = [DyeColor::PURPLE(), DyeColor::MAGENTA()];
                $rocketType = FireworkRocketType::STAR;
                break;
            case "awakened":
                $mainColors = [DyeColor::YELLOW(), DyeColor::ORANGE()];
                $fadeColors = [DyeColor::YELLOW(), DyeColor::ORANGE()];
                $rocketType = FireworkRocketType::STAR;
                break;
            case "ascendent":
                $mainColors = [DyeColor::PURPLE(), DyeColor::BLUE()];
                $fadeColors = [DyeColor::BLUE(), DyeColor::PURPLE()];
                $rocketType = FireworkRocketType::SMALL_BALL;
                break;
            case "eternal":
                $mainColors = [DyeColor::RED(), DyeColor::BLACK()];
                $fadeColors = [DyeColor::BLACK(), DyeColor::RED()];
                $rocketType = FireworkRocketType::LARGE_BALL;
                break;
            default:
                $mainColors = [DyeColor::WHITE()];
                $fadeColors = [DyeColor::WHITE()];
        }

        $explosions = [];
        for ($i = 0; $i < 3; $i++) {
            $explosions[] = new FireworkRocketExplosion(
                $rocketType,
                $mainColors, 
                $fadeColors, 
                true,
                true 
            );
        }

        $firework = new FireworkRocket($player->getLocation(), 20, $explosions);
        $firework->spawnToAll();
    }

    public function onEntityDeath(EntityDeathEvent $event): void {
        QuestManager::get("kill")?->onEntityDeath($event);
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        QuestManager::get("mining")?->onBlockBreak($event);
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        QuestManager::get("placing")?->onBlockPlace($event);
    }
}