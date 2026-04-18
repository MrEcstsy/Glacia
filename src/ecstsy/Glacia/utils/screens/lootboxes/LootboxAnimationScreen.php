<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\lootboxes;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat as C;

final class LootboxAnimationScreen extends BaseScreen {

    private const TOTAL_SECONDS = 8;
    private const UPDATE_INTERVAL_TICKS = 4;

    private InvMenu $menu;
    /** @var Item[] */
    private array $finalRewards;
    /** @var Item[] */
    private array $displayItems;
    private ?TaskHandler $task = null;
    private bool $finished = false;

    /**
     * @param array<string, mixed> $definition
     */
    public function __construct(private Player $viewer, private array $definition)
    {
        $this->menu = CustomSizedInvMenu::create(9);
        $this->menu->setName(C::colorize((string) ($definition["title"] ?? $definition["name"] ?? "&r&l&dLootbox")));
        $this->menu->setListener(InvMenu::readonly(
            fn(DeterministicInvMenuTransaction $transaction): bool => false
        ));
        $this->menu->setInventoryCloseListener(function(Player $player, Inventory $inventory): void {
            if (!$this->finished) {
                $this->finish(true);
            }
        });

        $this->finalRewards = LootboxRepository::rollRewards($this->definition, $viewer);
        if ($this->finalRewards === []) {
            $this->finalRewards = [
                VanillaItems::BARRIER()->setCustomName(C::colorize("&r&cMissing Reward"))
            ];
        }

        $this->displayItems = LootboxRepository::getDisplayItems($this->definition, $viewer);
        if ($this->displayItems === []) {
            $this->displayItems = $this->finalRewards;
        }

        $this->renderFrame(self::TOTAL_SECONDS);
    }

    public function display(Player $player): void
    {
        $this->menu->send($player);
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function(): void {
            $this->startAnimation();
        }), 1);
    }

    public function getMenu(): InvMenu {
        return $this->menu;
    }

    private function startAnimation(): void
    {
        if ($this->task !== null || $this->finished || !$this->viewer->isConnected()) {
            return;
        }

        $stepsPerSecond = intdiv(20, self::UPDATE_INTERVAL_TICKS);
        $maxSteps = self::TOTAL_SECONDS * $stepsPerSecond;
        $step = 0;

        $this->task = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() use (&$step, $stepsPerSecond, $maxSteps): void {
                if ($this->finished || !$this->viewer->isConnected()) {
                    $this->finish();
                    return;
                }

                $remainingSeconds = max(1, self::TOTAL_SECONDS - intdiv($step, $stepsPerSecond));
                $this->renderFrame($remainingSeconds);
                PlayerUtils::playSound($this->viewer, "random.orb");

                if ($step % $stepsPerSecond === 0) {
                    PlayerUtils::playSound($this->viewer, "note.hat");
                }

                $step++;
                if ($step >= $maxSteps) {
                    $this->finish();
                }
            }
        ), self::UPDATE_INTERVAL_TICKS);
    }

    private function finish(bool $closedEarly = false): void
    {
        if ($this->finished) {
            return;
        }

        $this->finished = true;
        if ($this->task !== null) {
            $this->task->cancel();
            $this->task = null;
        }

        $inventory = $this->menu->getInventory();
        $rewardSlots = $this->getRewardSlots(count($this->finalRewards));

        if (!$closedEarly) {
            $this->fillStaticSlots($inventory, 1);
            foreach ($rewardSlots as $index => $slot) {
                $inventory->setItem($slot, clone $this->finalRewards[$index]);
            }
        }

        $session = Loader::getPlayerManager()->getSession($this->viewer);
        foreach ($this->viewer->getInventory()->addItem(...array_map(static fn(Item $item): Item => clone $item, $this->finalRewards)) as $leftover) {
            if ($session !== null) {
                $session->addItemToCollection($leftover);
            } else {
                $this->viewer->getWorld()->dropItem($this->viewer->getPosition(), $leftover);
            }
        }

        $message = "&r&aLootbox opened! You received:";
        foreach ($this->finalRewards as $reward) {
            $message .= "\n&r&8- &r&ax" . $reward->getCount() . " " . $reward->getName();
        }
        $this->viewer->sendMessage(C::colorize($message));
        PlayerUtils::playSound($this->viewer, "random.levelup");
    }

    private function renderFrame(int $remainingSeconds): void
    {
        $inventory = $this->menu->getInventory();
        $this->fillStaticSlots($inventory, $remainingSeconds);

        foreach ($this->getRewardSlots(count($this->finalRewards)) as $slot) {
            $inventory->setItem($slot, clone $this->getRandomDisplayItem());
        }
    }

    private function fillStaticSlots(Inventory $inventory, int $remainingSeconds): void
    {
        $background = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" ");
        foreach (range(1, 7) as $slot) {
            $inventory->setItem($slot, clone $background);
        }

        $timerPane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::LIGHT_BLUE())->asItem();
        $timerPane->setCustomName(C::colorize("&r&l&b" . $remainingSeconds . "s"));
        $timerPane->setLore([
            C::colorize("&r&7Lootbox finishes in"),
            C::colorize("&r&f" . $remainingSeconds . "&7 second(s).")
        ]);
        $timerPane->setCount(max(1, min(64, $remainingSeconds)));

        $inventory->setItem(0, clone $timerPane);
        $inventory->setItem(8, clone $timerPane);
    }

    /**
     * @return int[]
     */
    private function getRewardSlots(int $count): array
    {
        return match (max(1, min(7, $count))) {
            1 => [4],
            2 => [3, 5],
            3 => [2, 4, 6],
            4 => [2, 3, 5, 6],
            5 => [1, 2, 4, 6, 7],
            6 => [1, 2, 3, 5, 6, 7],
            default => [1, 2, 3, 4, 5, 6, 7],
        };
    }

    private function getRandomDisplayItem(): Item
    {
        return clone $this->displayItems[array_rand($this->displayItems)];
    }
}
