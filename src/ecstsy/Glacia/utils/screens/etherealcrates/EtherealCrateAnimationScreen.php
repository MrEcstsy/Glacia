<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\etherealcrates;

use Closure;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\etherealcrates\EtherealCrateRepository;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat as C;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;

final class EtherealCrateAnimationScreen
{
    /** @var int[] */
    private const MAIN_REWARD_SLOTS = [12, 13, 14, 21, 22, 23, 30, 31, 32];
    private const BONUS_SLOT = 49;
    private const ROWS = 6;
    private const COLUMNS = 9;
    private const UPDATE_INTERVAL_TICKS = 2;
    /** @var int[][] */
    private const FINAL_ANIMATION_COLUMNS = [
        [0, 9, 18, 27, 36, 45],
        [1, 10, 19, 28, 37, 46],
        [2, 11, 20, 29, 38, 47],
        [3, 39, 48],
        [4, 40],
        [5, 41, 50],
        [6, 15, 24, 33, 42, 51],
        [7, 16, 25, 34, 43, 52],
        [8, 17, 26, 35, 44, 53],
    ];

    private InvMenu $menu;
    /** @var array<string, mixed> */
    private array $crate;
    /** @var Item[] */
    private array $displayItems = [];
    /** @var Item[] */
    private array $mainRewards = [];
    private ?Item $bonusReward = null;
    /** @var array<int, bool> */
    private array $claimedMain = [];
    private bool $bonusClaimed = false;
    /** @var array<int, bool> */
    private array $rolledMain = [];
    /** @var array<int, bool> */
    private array $rollingMain = [];
    /** @var array<int, TaskHandler> */
    private array $rollTasks = [];
    private ?TaskHandler $bonusRollTask = null;
    private ?TaskHandler $bonusUnlockTask = null;
    private bool $bonusRolling = false;
    private bool $bonusRolled = false;
    private bool $bonusUnlocking = false;
    /** @var string[] */
    private array $paneColors = [];
    /** @var array<string, string[]> */
    private array $sounds = [];
    private bool $committed = false;

    /**
     * @param array<string, mixed> $crate
     */
    public function __construct(private Player $viewer, array $crate, private ?Closure $onFirstRollCommit = null)
    {
        $this->crate = $crate;
        $this->paneColors = EtherealCrateRepository::getAnimationPaneColors($crate);
        $this->sounds = [
            "main-roll-start" => EtherealCrateRepository::getSoundSequence($crate, "main-roll-start", ["random.orb", "random.pop2"]),
            "main-roll-tick" => EtherealCrateRepository::getSoundSequence($crate, "main-roll-tick", ["random.click", "note.hat"]),
            "main-roll-finish" => EtherealCrateRepository::getSoundSequence($crate, "main-roll-finish", ["random.levelup"]),
            "bonus-roll-start" => EtherealCrateRepository::getSoundSequence($crate, "bonus-roll-start", ["random.orb", "mob.endermen.portal"]),
            "bonus-roll-tick" => EtherealCrateRepository::getSoundSequence($crate, "bonus-roll-tick", ["random.click", "random.pop2", "note.pling"]),
            "bonus-roll-finish" => EtherealCrateRepository::getSoundSequence($crate, "bonus-roll-finish", ["random.levelup", "random.anvil_use"]),
            "invalid-click" => EtherealCrateRepository::getSoundSequence($crate, "invalid-click", ["note.bass"]),
        ];
        $rolled = EtherealCrateRepository::rollMainRewards($crate, $viewer, count(self::MAIN_REWARD_SLOTS));
        $this->mainRewards = $rolled["items"] ?? [];
        $this->bonusReward = EtherealCrateRepository::rollBonusReward($crate, $viewer, $rolled["used_fingerprints"] ?? []);
        $this->displayItems = EtherealCrateRepository::getDisplayItems($crate, $viewer);
        if ($this->displayItems === []) {
            $preview = array_values(array_filter(
                $this->mainRewards,
                static fn($it): bool => $it !== null
            ));
            $this->displayItems = $preview !== [] ? $preview : [VanillaBlocks::BARRIER()->asItem()];
        }

        $this->menu = CustomSizedInvMenu::create(self::ROWS * self::COLUMNS);
        $this->menu->setName(C::colorize((string) ($crate["title"] ?? $crate["name"] ?? "&r&5Ethereal Crate")));
        $this->menu->setInventoryCloseListener(function(Player $player, Inventory $inventory): void {
            $this->grantUnclaimedAndClose();
        });

        $this->menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();

            $mainIndex = array_search($slot, self::MAIN_REWARD_SLOTS, true);
            if ($mainIndex !== false) {
                if (($this->claimedMain[$mainIndex] ?? false) === true || ($this->rollingMain[$mainIndex] ?? false)) {
                    $this->playSoundSequence("invalid-click");
                    return;
                }

                if (($this->rolledMain[$mainIndex] ?? false) === false) {
                    $this->startRollForMainSlot((int) $mainIndex);
                    return;
                }
                $this->playSoundSequence("invalid-click");
                return;
            }

            if (
                $slot === self::BONUS_SLOT &&
                $this->canClaimBonus() &&
                !$this->bonusRolling &&
                !$this->bonusRolled &&
                $this->bonusReward !== null
            ) {
                $this->startBonusRoll();
                return;
            }

            $this->playSoundSequence("invalid-click");
        }));

        $this->renderIdleFrame();
    }

    public function display(Player $player): void
    {
        $this->menu->send($player);
    }

    private function startRollForMainSlot(int $index): void
    {
        if (!$this->viewer->isConnected() || !isset(self::MAIN_REWARD_SLOTS[$index])) {
            return;
        }
        if (($this->rollingMain[$index] ?? false) || ($this->rolledMain[$index] ?? false)) {
            return;
        }

        if (!$this->commitIfNeeded()) {
            return;
        }
        $this->rollingMain[$index] = true;
        $this->playSoundSequence("main-roll-start");
        $slot = self::MAIN_REWARD_SLOTS[$index];
        $step = 0;
        $this->fillBackground($this->menu->getInventory());
        $this->renderWonItems();

        $this->rollTasks[$index] = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() use ($index, $slot, &$step): void {
                if (!$this->viewer->isConnected()) {
                    $this->cancelRollTask($index);
                    return;
                }

                $inventory = $this->menu->getInventory();
                $display = clone $this->displayItems[array_rand($this->displayItems)];
                $display->setCount(1);
                $this->safeSetItem($inventory, $slot, $display);
                $this->renderMainPathAnimation($index, $step);
                $this->playSoundSequence("main-roll-tick", $step);
                $step++;

                if ($step >= $this->getMainRollSteps()) {
                    $this->cancelRollTask($index);
                    $this->rollingMain[$index] = false;
                    $this->rolledMain[$index] = true;
                    $this->claimedMain[$index] = true;

                    $reward = $this->mainRewards[$index] ?? null;
                    if ($reward instanceof Item) {
                        $this->safeSetItem($inventory, $slot, clone $reward);
                        $this->grantItem($this->viewer, clone $reward);
                    }
                    $this->setPathToGray($index);
                    $this->playSoundSequence("main-roll-finish");
                    if ($this->canClaimBonus() && $this->bonusReward !== null) {
                        $this->startBonusUnlockAnimation();
                    } else {
                        $this->refreshBonusState();
                    }
                }
            }
        ), self::UPDATE_INTERVAL_TICKS);
    }

    private function cancelRollTask(int $index): void
    {
        if (isset($this->rollTasks[$index])) {
            $this->rollTasks[$index]->cancel();
            unset($this->rollTasks[$index]);
        }
    }

    private function refreshBonusState(): void
    {
        $inventory = $this->menu->getInventory();
        if ($this->bonusRolled) {
            $rolled = clone ($this->bonusReward ?? VanillaItems::AIR());
            $this->safeSetItem($inventory, self::BONUS_SLOT, $rolled);
            return;
        }

        if ($this->bonusUnlocking) {
            $unlocking = VanillaBlocks::ENDER_CHEST()->asItem();
            $unlocking->setCustomName(C::colorize(EtherealCrateRepository::getUiLabel($this->crate, "bonus-unlocking-title", "&r&eUnlocking Bonus...")));
            $unlocking->setLore(array_map(static fn(string $line): string => C::colorize($line), EtherealCrateRepository::getUiLore($this->crate, "bonus-unlocking", ["&r&7Final animation in progress."])));
            $this->safeSetItem($inventory, self::BONUS_SLOT, $unlocking);
            return;
        }

        if ($this->canClaimBonus() && !$this->bonusClaimed && $this->bonusReward !== null) {
            $bonus = VanillaBlocks::ENDER_CHEST()->asItem();
            $bonus->setCustomName(C::colorize(EtherealCrateRepository::getUiLabel($this->crate, "bonus-ready-title", "&r&6Roll Bonus Reward")));
            $bonus->setLore(array_map(static fn(string $line): string => C::colorize($line), EtherealCrateRepository::getUiLore($this->crate, "bonus-ready", ["&r&eClick to roll your bonus reward."])));
            $this->safeSetItem($inventory, self::BONUS_SLOT, $bonus);
            return;
        }

        if ($this->bonusClaimed) {
            $this->safeSetItem($inventory, self::BONUS_SLOT, VanillaItems::AIR());
            return;
        }

        $lockedBonus = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::RED())->asItem();
        $lockedBonus->setCustomName(C::colorize(EtherealCrateRepository::getUiLabel($this->crate, "bonus-locked-title", "&r&cBonus Locked")));
        $lockedBonus->setLore(array_map(static fn(string $line): string => C::colorize($line), EtherealCrateRepository::getUiLore($this->crate, "bonus-locked", ["&r&7Claim all 9 rewards first."])));
        $this->safeSetItem($inventory, self::BONUS_SLOT, $lockedBonus);
    }

    private function canClaimBonus(): bool
    {
        foreach (array_keys(self::MAIN_REWARD_SLOTS) as $index) {
            if (($this->claimedMain[$index] ?? false) === false) {
                return false;
            }
        }
        return true;
    }

    private function grantUnclaimedAndClose(): void
    {
        $this->cancelAllRollTasks();

        // If the player never rolled anything, keep the crate and grant nothing.
        if (!$this->committed) {
            $this->releaseReferences();
            return;
        }

        // Safety net: grant any unclaimed rewards on close/disconnect.
        foreach (self::MAIN_REWARD_SLOTS as $index => $_slot) {
            if (($this->claimedMain[$index] ?? false) === false && isset($this->mainRewards[$index])) {
                $this->grantItem($this->viewer, clone $this->mainRewards[$index]);
                $this->claimedMain[$index] = true;
            }
        }
        // If main rewards are complete, closing should still grant bonus even
        // when the player didn't manually click/roll the bonus chest yet.
        if ($this->canClaimBonus() && !$this->bonusClaimed && $this->bonusReward !== null) {
            $this->grantItem($this->viewer, clone $this->bonusReward);
            $this->bonusClaimed = true;
        }

        $this->releaseReferences();
    }

    private function cancelAllRollTasks(): void
    {
        foreach (array_keys($this->rollTasks) as $index) {
            $this->cancelRollTask((int) $index);
        }
        $this->bonusRollTask?->cancel();
        $this->bonusRollTask = null;
        $this->bonusUnlockTask?->cancel();
        $this->bonusUnlockTask = null;
    }

    private function releaseReferences(): void
    {
        // Break closure/object retention chains once the menu lifecycle is complete.
        $this->menu->setListener(null);
        $this->menu->setInventoryCloseListener(null);
        $this->onFirstRollCommit = null;

        $this->displayItems = [];
        $this->mainRewards = [];
        $this->bonusReward = null;
        $this->rollingMain = [];
        $this->rolledMain = [];
        $this->claimedMain = [];
        $this->sounds = [];
        $this->bonusUnlocking = false;
    }

    private function commitIfNeeded(): bool
    {
        if ($this->committed) {
            return true;
        }

        if ($this->onFirstRollCommit !== null) {
            $committed = (bool) ($this->onFirstRollCommit)();
            if (!$committed) {
                $this->viewer->sendMessage(C::colorize("&r&cYour ethereal crate could not be consumed. Try again."));
                $this->playSoundSequence("invalid-click");
                return false;
            }
        }
        $this->committed = true;
        return true;
    }

    private function grantItem(Player $player, Item $item): void
    {
        $session = Loader::getPlayerManager()->getSession($player);
        foreach ($player->getInventory()->addItem($item) as $leftover) {
            if ($session !== null) {
                $session->addItemToCollection($leftover);
            } else {
                $player->getWorld()->dropItem($player->getPosition(), $leftover);
            }
        }
    }

    private function renderIdleFrame(): void
    {
        $inventory = $this->menu->getInventory();
        $this->fillBackground($inventory);

        $this->renderCenterPane();

        foreach (self::MAIN_REWARD_SLOTS as $slot) {
            $ender = VanillaBlocks::ENDER_CHEST()->asItem();
            $ender->setCustomName(C::colorize("&r&5Roll Reward"));
            $ender->setLore([C::colorize("&r&7Click to start this reward roll.")]);
            $this->safeSetItem($inventory, $slot, $ender);
        }

        $this->refreshBonusState();
    }

    private function startBonusRoll(): void
    {
        if (
            $this->bonusReward === null ||
            $this->bonusRolling ||
            $this->bonusRolled ||
            !$this->canClaimBonus() ||
            !$this->viewer->isConnected()
        ) {
            return;
        }
        if (!$this->commitIfNeeded()) {
            return;
        }
        $this->bonusRolling = true;
        $this->playSoundSequence("bonus-roll-start");
        $step = 0;
        $displayPool = $this->displayItems !== [] ? $this->displayItems : [$this->bonusReward];
        $inventory = $this->menu->getInventory();

        $this->bonusRollTask = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() use (&$step, $displayPool, $inventory): void {
                if (!$this->viewer->isConnected()) {
                    $this->bonusRollTask?->cancel();
                    $this->bonusRollTask = null;
                    return;
                }

                $preview = clone $displayPool[array_rand($displayPool)];
                $preview->setCount(1);
                $this->safeSetItem($inventory, self::BONUS_SLOT, $preview);
                $this->playSoundSequence("bonus-roll-tick", $step);
                $step++;

                if ($step >= $this->getMainRollSteps()) {
                    $this->bonusRollTask?->cancel();
                    $this->bonusRollTask = null;
                    $this->bonusRolling = false;
                    $this->bonusRolled = true;
                    $this->bonusClaimed = true;

                    $final = clone $this->bonusReward;
                    $this->safeSetItem($inventory, self::BONUS_SLOT, $final);
                    $this->grantItem($this->viewer, clone $this->bonusReward);
                    $this->playSoundSequence("bonus-roll-finish");
                }
            }
        ), self::UPDATE_INTERVAL_TICKS);
    }

    /**
     * @return int[]
     */
    private function getPathForMainIndex(int $index): array
    {
        $default = match ($index) {
            0 => [3, 39, 48, 9, 10, 11, 15, 16, 17],
            1 => [4, 40, 9, 10, 11, 15, 16, 17],
            2 => [5, 41, 50, 9, 10, 11, 15, 16, 17],
            3 => [3, 39, 48, 18, 19, 20, 24, 25, 26],
            4 => [4, 40, 18, 19, 20, 24, 25, 26],
            5 => [5, 41, 50, 18, 19, 20, 24, 25, 26],
            6 => [3, 39, 48, 27, 28, 29, 33, 34, 35],
            7 => [4, 40, 27, 28, 29, 33, 34, 35],
            8 => [5, 41, 50, 27, 28, 29, 33, 34, 35],
            default => [],
        };

        return EtherealCrateRepository::getAnimationPathForSlot($this->crate, self::MAIN_REWARD_SLOTS[$index] ?? -1, $default);
    }

    /**
     * @return int[]
     */
    private function getCornerFillForMainIndex(int $index): array
    {
        return EtherealCrateRepository::getAnimationCornerFillForSlot($this->crate, self::MAIN_REWARD_SLOTS[$index] ?? -1, []);
    }

    private function renderMainPathAnimation(int $index, int $step): void
    {
        $inventory = $this->menu->getInventory();
        $path = $this->getPathForMainIndex($index);
        if ($path === []) {
            return;
        }

        foreach ($path as $slot) {
            if (in_array($slot, self::MAIN_REWARD_SLOTS, true) || $slot === self::BONUS_SLOT) {
                continue;
            }
            $color = $this->mapDyeColor($this->paneColors[array_rand($this->paneColors)] ?? "gray");
            $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor($color)->asItem()->setCustomName(" ");
            $this->safeSetItem($inventory, $slot, $pane);
        }

        // Optional smoother visual pass to fill corners/holes around configured paths.
        foreach ($this->getCornerFillForMainIndex($index) as $slot) {
            if (in_array($slot, self::MAIN_REWARD_SLOTS, true) || $slot === self::BONUS_SLOT) {
                continue;
            }
            $color = $this->mapDyeColor($this->paneColors[array_rand($this->paneColors)] ?? "gray");
            $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor($color)->asItem()->setCustomName(" ");
            $this->safeSetItem($inventory, $slot, $pane);
        }
    }

    private function setPathToGray(int $index): void
    {
        $inventory = $this->menu->getInventory();
        foreach ($this->getPathForMainIndex($index) as $slot) {
            if (in_array($slot, self::MAIN_REWARD_SLOTS, true) || $slot === self::BONUS_SLOT) {
                continue;
            }
            $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY())->asItem()->setCustomName(" ");
            $this->safeSetItem($inventory, $slot, $pane);
        }
        foreach ($this->getCornerFillForMainIndex($index) as $slot) {
            if (in_array($slot, self::MAIN_REWARD_SLOTS, true) || $slot === self::BONUS_SLOT) {
                continue;
            }
            $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY())->asItem()->setCustomName(" ");
            $this->safeSetItem($inventory, $slot, $pane);
        }
    }

    private function renderWonItems(): void
    {
        $inventory = $this->menu->getInventory();
        foreach (self::MAIN_REWARD_SLOTS as $index => $slot) {
            if (($this->claimedMain[$index] ?? false)) {
                $reward = $this->mainRewards[$index] ?? null;
                if ($reward instanceof Item) {
                    $this->safeSetItem($inventory, $slot, clone $reward);
                }
                continue;
            }
            if (($this->rollingMain[$index] ?? false)) {
                continue;
            }
            if (($this->rolledMain[$index] ?? false)) {
                $reward = $this->mainRewards[$index] ?? null;
                if ($reward instanceof Item) {
                    $this->safeSetItem($inventory, $slot, clone $reward);
                }
                continue;
            }
            $ender = VanillaBlocks::ENDER_CHEST()->asItem();
            $ender->setCustomName(C::colorize(EtherealCrateRepository::getUiLabel($this->crate, "roll-title", "&r&5Roll Reward")));
            $ender->setLore(array_map(static fn(string $line): string => C::colorize($line), EtherealCrateRepository::getUiLore($this->crate, "roll", ["&r&7Click to start this reward roll."])));
            $this->safeSetItem($inventory, $slot, $ender);
        }
    }

    private function playSoundSequence(string $key, int $tick = 0): void
    {
        $sequence = $this->sounds[$key] ?? [];
        if ($sequence === []) {
            return;
        }

        $index = $tick % count($sequence);
        $sound = trim($sequence[$index] ?? "");
        if ($sound === "") {
            return;
        }

        PlayerUtils::playSound($this->viewer, $sound);
    }

    private function fillBackground(Inventory $inventory): void
    {
        $rewardSlots = array_fill_keys(self::MAIN_REWARD_SLOTS, true);
        $rewardSlots[self::BONUS_SLOT] = true;

        $rows = self::ROWS;
        $cols = self::COLUMNS;
        for ($slot = 0; $slot < $rows * $cols; $slot++) {
            if (isset($rewardSlots[$slot])) {
                continue;
            }

            $color = (($slot + intdiv($slot, 9)) % 2 === 0) ? DyeColor::BLACK() : DyeColor::GRAY();
            $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor($color)->asItem()->setCustomName(" ");
            $this->safeSetItem($inventory, $slot, $pane);
        }
    }

    private function safeSetItem(Inventory $inventory, int $slot, Item $item): void
    {
        if ($slot < 0 || $slot >= $inventory->getSize()) {
            return;
        }

        $inventory->setItem($slot, $item);
    }

    private function startBonusUnlockAnimation(): void
    {
        if ($this->bonusUnlocking || $this->bonusRolled || $this->bonusClaimed) {
            return;
        }

        $this->bonusUnlocking = true;
        $this->refreshBonusState();
        $ticksRemaining = EtherealCrateRepository::getFinalAnimationSeconds($this->crate);
        $colorIndex = 0;

        $this->bonusUnlockTask = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() use (&$ticksRemaining, &$colorIndex): void {
                if (!$this->viewer->isConnected()) {
                    $this->bonusUnlockTask?->cancel();
                    $this->bonusUnlockTask = null;
                    return;
                }

                if ($ticksRemaining <= 0) {
                    $this->bonusUnlockTask?->cancel();
                    $this->bonusUnlockTask = null;
                    $this->bonusUnlocking = false;
                    $this->fillAnimationColumns(DyeColor::GRAY());
                    $this->renderWonItems();
                    $this->renderCenterPane();
                    $this->refreshBonusState();
                    $this->playSoundSequence("bonus-roll-finish");
                    return;
                }

                $colorName = $this->paneColors[$colorIndex % max(1, count($this->paneColors))] ?? "gray";
                $this->fillAnimationColumns($this->mapDyeColor($colorName));
                $this->renderWonItems();
                $this->renderCenterPane();
                $this->refreshBonusState();
                $this->playSoundSequence("main-roll-tick", $colorIndex);
                $colorIndex++;
                $ticksRemaining--;
            }
        ), 20);
    }

    private function fillAnimationColumns(DyeColor $color): void
    {
        $inventory = $this->menu->getInventory();
        foreach (self::FINAL_ANIMATION_COLUMNS as $column) {
            foreach ($column as $slot) {
                $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor($color)->asItem()->setCustomName(" ");
                $this->safeSetItem($inventory, $slot, $pane);
            }
        }
    }

    private function renderCenterPane(): void
    {
        $inventory = $this->menu->getInventory();
        $center = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY())->asItem();
        $center->setCustomName(C::colorize(EtherealCrateRepository::getUiLabel($this->crate, "center-title", "&r&7Click ender chests to roll")));
        $center->setLore(array_map(static fn(string $line): string => C::colorize($line), EtherealCrateRepository::getUiLore($this->crate, "center", ["&r&8Rewards are auto-claimed after each roll."])));
        $this->safeSetItem($inventory, 4, $center);
    }

    private function getMainRollSteps(): int
    {
        return max(18, EtherealCrateRepository::getAnimationSeconds($this->crate) * (20 / self::UPDATE_INTERVAL_TICKS));
    }

    private function mapDyeColor(string $name): DyeColor
    {
        return match (strtolower(trim($name))) {
            "white" => DyeColor::WHITE(),
            "orange" => DyeColor::ORANGE(),
            "magenta" => DyeColor::MAGENTA(),
            "light_blue", "lightblue", "cyan" => DyeColor::LIGHT_BLUE(),
            "yellow" => DyeColor::YELLOW(),
            "lime", "green" => DyeColor::LIME(),
            "pink" => DyeColor::PINK(),
            "gray", "grey" => DyeColor::GRAY(),
            "light_gray", "light_grey", "silver" => DyeColor::LIGHT_GRAY(),
            "purple" => DyeColor::PURPLE(),
            "blue" => DyeColor::BLUE(),
            "brown" => DyeColor::BROWN(),
            "red" => DyeColor::RED(),
            "black" => DyeColor::BLACK(),
            default => DyeColor::BLACK(),
        };
    }
}
