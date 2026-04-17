<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\droppackages;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\droppackages\DropPackageRarityRoller;
use ecstsy\Glacia\server\droppackages\DropPackageRewardPool;
use ecstsy\Glacia\server\droppackages\DropPackageSession;
use ecstsy\Glacia\tasks\DropPackageAnimationTask;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class DropPackageScreen extends BaseScreen {

    private InvMenu $menu;

    private const BORDERS = [
        0, 1, 2, 3, 4, 5, 6, 7, 8,
        9, 18, 27, 36, 45,
        17, 26, 35, 44, 53,
        46, 47, 48, 49, 50, 51, 52
    ];

    private const REWARD_SLOTS = [
        10, 11, 12, 13, 14, 15, 16,
        19, 20, 21, 22, 23, 24, 25,
        28, 29, 30, 31, 32, 33, 34,
        37, 38, 39, 40, 41, 42, 43
    ];

    public function __construct(private readonly DropPackageSession $session){
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize($this->session->getTitle()));

        $this->setupInitialContents();

        $this->menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void{
            $player = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();

            if(!in_array($slot, self::REWARD_SLOTS, true)){
                return;
            }

            switch($this->session->getPhase()){
                case DropPackageSession::PHASE_SELECTING:
                    $this->handleSelectingClick($player, $slot);
                    break;

                case DropPackageSession::PHASE_READY_TO_REVEAL:
                    $this->handleRevealClick($player, $slot);
                    break;
            }
        }));

        $this->menu->setInventoryCloseListener(function(Player $player): void {
                $this->handleClose($player);
        });
    }

    private function handleClose(Player $player): void {
        if ($this->session->hasGrantedRewards()) {
            return;
        }

        $this->autoCompleteSelections($player);

        if ($this->session->getPhase() === DropPackageSession::PHASE_SELECTING) {
            $this->rollAllRewards($player);
        }

        $this->giveAllRewards($player);
        $this->session->markRewardsGranted();
        $this->session->setPhase(DropPackageSession::PHASE_COMPLETE);
    }

    private function autoCompleteSelections(Player $player): void {
        if ($this->session->getSelectedCount() >= $this->session->getPickAmount()){
            return;
        }

        $available = array_values(array_filter(self::REWARD_SLOTS, fn(int $slot) => !$this->session->isSelected($slot)));

        shuffle($available);

        $needed = $this->session->getPickAmount() - $this->session->getSelectedCount();

        for ($i = 0; $i < $needed; $i++) {
            if (!isset($available[$i])) {
                break;
            }

            $this->session->addSelection($available[$i]);
        }

        $this->session->lockSelections();
    }

    private function setupInitialContents(): void{
        $inventory = $this->menu->getInventory();

        foreach(self::BORDERS as $slot){
            $inventory->setItem($slot, $this->getBorderItem());
        }

        foreach(self::REWARD_SLOTS as $index => $slot){
            $inventory->setItem($slot, $this->getUnselectedMysteryItem($index + 1));
        }
    }

    private function handleSelectingClick(Player $player, int $slot): void{
        if($this->session->isSelected($slot)){
            $this->session->removeSelection($slot);
            $this->menu->getInventory()->setItem($slot, $this->getUnselectedMysteryItem($this->getDisplayNumberForSlot($slot)));
            PlayerUtils::playSound($player, "random.click");
            return;
        }

        if($this->session->getSelectedCount() >= $this->session->getPickAmount()){
            return;
        }

        $this->session->addSelection($slot);
        $this->menu->getInventory()->setItem($slot, $this->getSelectedMysteryItem($this->getDisplayNumberForSlot($slot)));
        PlayerUtils::playSound($player, "item.book.page_turn");

        if($this->session->getSelectedCount() >= $this->session->getPickAmount()){
            $this->session->lockSelections();
            $this->rollAllRewards($player);
            $this->session->setPhase(DropPackageSession::PHASE_ANIMATING);

            Loader::getInstance()->getScheduler()->scheduleRepeatingTask(
                new DropPackageAnimationTask($player, $this, $this->session),
                10
            );
        }
    }

    private function rollAllRewards(Player $player): void{
        foreach(self::REWARD_SLOTS as $slot){
            $rarity = DropPackageRarityRoller::roll($this->session->getPackageType());

            $rolled = DropPackageRewardPool::rollReward(
                $this->session->getPackageType(),
                $rarity,
                $player
            );

            $this->session->setSlotRarity($slot, $rarity);
            $this->session->setSlotReward($slot, $rolled["item"]);
        }
    }

    private function rollSelectedRewards(Player $player): void{
        foreach($this->session->getSelectedSlots() as $slot){
            $rarity = DropPackageRarityRoller::roll($this->session->getPackageType());

            $rolled = DropPackageRewardPool::rollReward(
                $this->session->getPackageType(),
                $rarity,
                $player
            );

            $this->session->setSlotRarity($slot, $rarity);
            $this->session->setSlotReward($slot, $rolled["item"]);
        }
    }

    private function handleRevealClick(Player $player, int $slot): void{
        if(!$this->session->isSelected($slot)){
            return;
        }

        if($this->session->isRewardRevealed($slot)){
            return;
        }

        $reward = $this->session->getSlotReward($slot);
        if($reward === null){
            return;
        }

        $this->session->markRewardRevealed($slot);
        $this->menu->getInventory()->setItem($slot, clone $reward);

        if($this->session->areAllSelectedRewardsRevealed()){
            $this->giveAllRewards($player);
            $this->session->setPhase(DropPackageSession::PHASE_COMPLETE);
        }
    }


    private function giveAllRewards(Player $player): void{
        if ($this->session->hasGrantedRewards()) {
            return;
        }

        foreach($this->session->getSelectedSlots() as $slot){
            $reward = $this->session->getSlotReward($slot);
            if($reward === null){
                continue;
            }

            $leftovers = $player->getInventory()->addItem(clone $reward);
            PlayerUtils::playSound($player, "random.orb");
            foreach($leftovers as $leftover){
                $session = PlayerManager::getInstance()->getSession($player);
                $session->addItemToCollection($leftover);
            }
        }

        $this->session->markRewardsGranted();
    }

        public function finalizeAfterAnimation(): void{
        $inventory = $this->menu->getInventory();

        foreach(self::REWARD_SLOTS as $slot){
            if($this->session->isSelected($slot)){
                $inventory->setItem($slot, $this->getRevealPaneItem($slot));
            }else{
                $inventory->setItem($slot, $this->getBorderItem());
            }
        }

        $this->session->setPhase(DropPackageSession::PHASE_READY_TO_REVEAL);
    }

    public function getRewardSlots(): array{
        return self::REWARD_SLOTS;
    }

    public function setSlotItem(int $slot, Item $item): void{
        $this->menu->getInventory()->setItem($slot, $item);
    }

    public function getSelectedMysteryItemForSlot(int $slot): Item{
        return $this->getSelectedMysteryItem($this->getDisplayNumberForSlot($slot));
    }

    public function getBorderPaneItem(): Item{
        return $this->getBorderItem();
    }

    public function getRandomAnimatedRarityItem(): Item{
        $rarities = ["simple", "unique", "elite", "ultimate", "legendary"];
        return $this->createRarityPane($rarities[array_rand($rarities)], false);
    }

    public function getFinalRarityItem(int $slot): Item{
        $rarity = strtolower($this->session->getSlotRarity($slot) ?? "simple");
        return $this->createRarityPane($rarity, false);
    }

    public function getPotentialRewardItem(int $slot): ?Item{
        return $this->session->getSlotReward($slot);
    }

    private function createRarityPane(string $rarity, bool $clickToReveal): Item{
        $material = match($rarity){
            "unique" => "lime_stained_glass_pane",
            "elite" => "light_blue_stained_glass_pane",
            "ultimate" => "yellow_stained_glass_pane",
            "legendary" => "orange_stained_glass_pane",
            default => "white_stained_glass_pane"
        };

        $name = match($rarity){
            "unique" => "&r&l&aUnique Mystery Item",
            "elite" => "&r&l&bElite Mystery Item",
            "ultimate" => "&r&l&eUltimate Mystery Item",
            "legendary" => "&r&l&6Legendary Mystery Item",
            default => "&r&l&fSimple Mystery Item"
        };

        $item = StringToItemParser::getInstance()->parse($material);
        $item->setCustomName(C::colorize($name));

        $item->setLore($clickToReveal ? [
            C::colorize("&r&7Click here to reveal a " . $rarity . " item"),
            C::colorize("&r&7from the " . $this->session->getDisplayName())
        ] : [
            C::colorize("&r&7This slot contains a " . $rarity . " reward")
        ]);

        return $item;
    }

    private function prepareRevealState(): void{
        $inventory = $this->menu->getInventory();

        foreach(self::REWARD_SLOTS as $slot){
            if($this->session->isSelected($slot)){
                $inventory->setItem($slot, $this->getRevealPaneItem($slot));
            }else{
                $inventory->setItem($slot, $this->getBorderItem());
            }
        }
    }

    private function getDisplayNumberForSlot(int $slot): int{
        $index = array_search($slot, self::REWARD_SLOTS, true);
        return ($index === false ? 0 : $index + 1);
    }

    private function getBorderItem(): Item{
        $item = StringToItemParser::getInstance()->parse("black_stained_glass_pane");
        $item->setCustomName(C::colorize("&r "));
        return $item;
    }

    private function getUnselectedMysteryItem(int $number): Item{
        $item = StringToItemParser::getInstance()->parse("white_stained_glass_pane");
        $item->setCustomName(C::colorize("&r&l&f??? &r&7#" . $number));
        $item->setLore([
            C::colorize("&r&7Choose " . $this->session->getPickAmount() . " mystery panels, and"),
            C::colorize("&r&7your loot will be revealed")
        ]);
        return $item;
    }

    private function getSelectedMysteryItem(int $number): Item{
        $item = StringToItemParser::getInstance()->parse("trapped_chest");
        $item->setCustomName(C::colorize("&r&l&fMystery Item #" . $number));
        $item->setLore([
            C::colorize("&r&7You have selected this mystery item")
        ]);
        return $item;
    }

    private function getRevealPaneItem(int $slot): Item{
        $rarity = strtolower($this->session->getSlotRarity($slot) ?? "simple");

        $material = match($rarity){
            "unique" => "lime_stained_glass_pane",
            "elite" => "light_blue_stained_glass_pane",
            "ultimate" => "yellow_stained_glass_pane",
            "legendary" => "orange_stained_glass_pane",
            default => "white_stained_glass_pane"
        };

        $name = match($rarity){
            "unique" => "&r&l&aUnique Mystery Item",
            "elite" => "&r&l&bElite Mystery Item",
            "ultimate" => "&r&l&eUltimate Mystery Item",
            "legendary" => "&r&l&6Legendary Mystery Item",
            default => "&r&l&fSimple Mystery Item"
        };

        $item = StringToItemParser::getInstance()->parse($material);
        $item->setCustomName(C::colorize($name));
        $item->setLore([
            C::colorize("&r&7Click here to reveal a " . $rarity . " item"),
            C::colorize("&r&7from the " . $this->session->getDisplayName())
        ]);

        return $item;
    }

    public static function display(Player $player, DropPackageSession $session): void
    {
        (new self($session))->getMenu()->send($player);
    }


    public function getMenu(): InvMenu{
        return $this->menu;
    }
}