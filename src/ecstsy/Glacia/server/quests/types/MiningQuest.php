<?php
declare(strict_types=1);

namespace ecstsy\Glacia\server\quests\types;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\player\Player;
use ecstsy\Glacia\server\quests\QuestInstance;

final class MiningQuest {

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getBlock()->asItem();
        QuestInstance::addProgress($player, "mine", $item, 1);
    }
}
