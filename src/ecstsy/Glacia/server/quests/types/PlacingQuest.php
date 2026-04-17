<?php
declare(strict_types=1);

namespace ecstsy\Glacia\server\quests\types;

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\player\Player;
use ecstsy\Glacia\server\quests\QuestInstance;

final class PlacingQuest {

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        QuestInstance::addProgress($player, "place", $item, 1);
    }
}
