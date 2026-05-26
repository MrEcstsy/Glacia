<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\pickaxe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\InteractEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\player\PlayerInteractEvent;

final class ObsidianDestroyerEnchant implements InteractEnchantmentHandler {
    /** @var array<string, int> */
    private static array $cooldowns = [];

    public static function handle(PlayerInteractEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();

        if ($event->getAction() !== PlayerInteractEvent::LEFT_CLICK_BLOCK) return;
        if (!CustomEnchantmentManager::hasEnchantment($item, "obsidiandestroyer")) return;
        if ($block->getTypeId() !== BlockTypeIds::OBSIDIAN) return;

        $key = strtolower($player->getName());
        $chance = EnchantTuningManager::chance("obsidiandestroyer", $level, 5.0 * $level);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("obsidiandestroyer", $level, 0);
        $result = EnchantProcManager::attempt(
            $player,
            "obsidiandestroyer",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => max(0, (self::$cooldowns[$key] ?? 0) - time())
        );
        if (!$result->success) return;

        $position = $block->getPosition();
        $world = $position->getWorld();
        $world->setBlock($position, VanillaBlocks::AIR());
        $world->dropItem($position, VanillaBlocks::OBSIDIAN()->asItem());
        if ($cooldownSeconds > 0) {
            self::$cooldowns[$key] = time() + $cooldownSeconds;
        }
        EnchantProcManager::debugAppliedValues($player, $result, [
            "block" => "obsidian",
        ]);

        $event->cancel(); 
    }
}
