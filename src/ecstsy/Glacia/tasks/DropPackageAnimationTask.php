<?php

declare(strict_types=1);

namespace ecstsy\Glacia\tasks;

use ecstsy\Glacia\server\droppackages\DropPackageSession;
use ecstsy\Glacia\utils\screens\droppackages\DropPackageScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;

final class DropPackageAnimationTask extends Task {

    /** @var int[] */
    private array $unselectedSlots = [];

    /** @var int[] */
    private array $currentBatch = [];

    private int $index = 0;
    private int $stage = 0;

    private const STAGE_REVEAL_RARITIES = 0;
    private const STAGE_REVEAL_REWARDS = 1;
    private const STAGE_FINISH = 2;

    private const BATCH_SIZE = 4;

    public function __construct(
        private readonly Player $player,
        private readonly DropPackageScreen $screen,
        private readonly DropPackageSession $session
    ){
        foreach($this->screen->getRewardSlots() as $slot){
            if(!$this->session->isSelected($slot)){
                $this->unselectedSlots[] = $slot;
            } else {
                $this->screen->setSlotItem($slot, $this->screen->getSelectedMysteryItemForSlot($slot));
            }
        }

        shuffle($this->unselectedSlots);
    }

    public function onRun(): void{
        if(!$this->player->isConnected()){
            $this->getHandler()?->cancel();
            return;
        }

        if($this->session->getPhase() !== DropPackageSession::PHASE_ANIMATING){
            $this->getHandler()?->cancel();
            return;
        }

        switch($this->stage){
            case self::STAGE_REVEAL_RARITIES:
                $this->revealNextBatchRarities();
                break;

            case self::STAGE_REVEAL_REWARDS:
                $this->revealNextBatchRewards();
                break;

            case self::STAGE_FINISH:
                $this->screen->finalizeAfterAnimation();
                PlayerUtils::playSound($this->player, "item.book.page_turn");
                $this->getHandler()?->cancel();
                break;
        }
    }

    private function revealNextBatchRarities(): void{
        if($this->index >= count($this->unselectedSlots)){
            $this->index = 0; 
            $this->stage = self::STAGE_REVEAL_REWARDS;
            return;
        }

        $batch = array_slice($this->unselectedSlots, $this->index, self::BATCH_SIZE);

        foreach($batch as $slot){
            $this->screen->setSlotItem($slot, $this->screen->getFinalRarityItem($slot));
        }

        PlayerUtils::playSound($this->player, "random.click");
        $this->index += count($batch);
    }

    private function revealNextBatchRewards(): void{
        if($this->index >= count($this->unselectedSlots)){
            $this->stage = self::STAGE_FINISH;
            return;
        }

        $batch = array_slice($this->unselectedSlots, $this->index, self::BATCH_SIZE);

        foreach($batch as $slot){
            $reward = $this->screen->getPotentialRewardItem($slot);
            if($reward !== null){
                $this->screen->setSlotItem($slot, clone $reward);
            }
        }

        PlayerUtils::playSound($this->player, "random.click");
        $this->index += count($batch);
    }
}
