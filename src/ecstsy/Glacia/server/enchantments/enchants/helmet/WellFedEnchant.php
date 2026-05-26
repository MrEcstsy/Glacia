<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\PlayerItemConsumeEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class WellFedEnchant implements PlayerItemConsumeEnchantmentHandler {

    /** @var array<string, int> */
    private static array $cooldowns = [];

    public static function handle(Event $event, CustomEnchantment $enchant, int $level): void
    {
        if (!$event instanceof PlayerItemConsumeEvent) {
            return;
        }

        if ($event->isCancelled()) {
            return;
        }

        $player = $event->getPlayer();
        if (!$player instanceof Player) {
            return;
        }

        $cooldownKey = strtolower($player->getName());
        $chance = EnchantTuningManager::chance("wellfed", $level, 20.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("wellfed", $level, 3);
        $result = EnchantProcManager::attempt(
            $player,
            "wellfed",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => max(0, (self::$cooldowns[$cooldownKey] ?? 0) - time())
        );
        if (!$result->success) {
            return;
        }

        $restoreMode = strtolower(trim((string) EnchantTuningManager::getValue("wellfed", "restore-mode", "full")));
        self::$cooldowns[$cooldownKey] = time() + $cooldownSeconds;
        $playerName = $player->getName();
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function () use ($player, $playerName, $restoreMode, $level, $result): void {
                if (!$player->isConnected() || $player->isClosed() || $player->getName() !== $playerName) {
                    return;
                }

                $foodManager = $player->getHungerManager();
                $beforeFood = $foodManager->getFood();
                $appliedFood = 0;

                if ($restoreMode === "full") {
                    $foodManager->setFood($foodManager->getMaxFood());
                    $appliedFood = $foodManager->getFood() - $beforeFood;
                } else {
                    $foodRestore = max(0, EnchantTuningManager::valueInt("wellfed", "food-restore", $level, 1));
                    $foodManager->setFood(min($foodManager->getMaxFood(), $beforeFood + $foodRestore));
                    $appliedFood = $foodManager->getFood() - $beforeFood;
                }

                EnchantProcManager::debugAppliedValues($player, $result, [
                    "restore_mode" => $restoreMode,
                    "food_before" => $beforeFood,
                    "food_after" => $foodManager->getFood(),
                    "applied_food" => $appliedFood,
                ]);
            }
        ), 1);
    }

    public static function clearPlayer(Player $player): void
    {
        unset(self::$cooldowns[strtolower($player->getName())]);
    }
}
