<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\utils\DyeColor;
use pocketmine\entity\object\FireworkRocket;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\item\FireworkRocketType;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\LavaParticle;

final class QuestListener implements Listener {

    public function onPlayerItemUse(PlayerItemUseEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $nbt = $item->getNamedTag();

        if ($nbt === null || !$nbt->getTag("glacia_quest_tier")) {
            return;
        }

        $tier = $nbt->getString("glacia_quest_tier");
        $completeTag = $nbt->getTag("glacia_quest_complete");
        $isComplete = ($completeTag instanceof ByteTag || $completeTag instanceof IntTag) && $completeTag->getValue() === 1;

        if (!$isComplete) {
            $player->sendMessage(C::colorize("&r&cThis quest is not complete yet."));
            PlayerUtils::playSound($player, "block.lava.pop");
            $player->getWorld()->addParticle($player->getPosition()->add(0, 1.5, 0), new LavaParticle());
            return;
        }

        $tierData = QuestInstance::getTierData($tier);
        if ($tierData === null) {
            $player->sendMessage(C::colorize("&r&cThis quest reward is invalid."));
            return;
        }

        $reward = QuestInstance::rollReward($tier, $player);
        if ($reward === null) {
            $player->sendMessage(C::colorize("&r&cNo rewards are configured for this quest tier."));
            return;
        }

        $this->giveOrDropReward($player, $reward);

        $faction = Loader::getPlayerManager()->getSession($player)?->getFaction();
        if (Loader::getFactionUpgradeManager()->rollQuestBonus($faction)) {
            $bonusReward = QuestInstance::rollReward($tier, $player);
            if ($bonusReward !== null) {
                $this->giveOrDropReward($player, $bonusReward);
                $player->sendMessage(C::colorize("&r&6Glory Surge &8| &r&eYou received a bonus quest reward."));
            }
        }

        $item->pop();
        $player->getInventory()->setItemInHand($item);

        $player->sendMessage(C::colorize("&r&aYou claimed a reward from the &f" . $tierData["display"] . " &atier."));
        PlayerUtils::playSound($player, "random.levelup");
        $this->launchTierFireworks($player, $tier);
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
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void
    {
        QuestInstance::addProgress($event->getPlayer(), "mine", $event->getBlock()->asItem(), 1);
    }

    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        QuestInstance::addProgress($event->getPlayer(), "place", $event->getItem(), 1);
    }

    private function giveOrDropReward(Player $player, \pocketmine\item\Item $reward): void
    {
        foreach ($player->getInventory()->addItem($reward) as $leftover) {
            $player->getWorld()->dropItem($player->getPosition(), $leftover);
        }
    }

    private function launchTierFireworks(Player $player, string $tier): void
    {
        switch ($tier) {
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
                $rocketType = FireworkRocketType::SMALL_BALL;
                break;
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

        (new FireworkRocket($player->getLocation(), 20, $explosions))->spawnToAll();
    }
}
