<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class TricksterEnchant implements EntityDamageByEntityEnchantmentHandler {

    /** @var array<string, int> */
    private static array $cooldowns = [];

    private const DEFAULT_COOLDOWN = 20;
    private const DEFAULT_BASE_CHANCE = 4;
    private const DEFAULT_PER_LEVEL_CHANCE = 1;
    private const DEFAULT_MAX_CHANCE = 12;

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $attacker = $event->getDamager();

        if (!$victim instanceof Player || !$attacker instanceof Player) {
            return;
        }

        if (self::isOnCooldown($victim)) {
            return;
        }

        $baseChance = max(0, EnchantTuningManager::getInt("trickster", "base-chance", self::DEFAULT_BASE_CHANCE));
        $perLevelChance = max(0, EnchantTuningManager::getInt("trickster", "per-level-chance", self::DEFAULT_PER_LEVEL_CHANCE));
        $maxChance = max(1, EnchantTuningManager::getInt("trickster", "max-chance", self::DEFAULT_MAX_CHANCE));
        $chance = min(100, min($maxChance, $baseChance + ($level * $perLevelChance)));

        if (mt_rand(1, 100) > $chance) {
            return;
        }

        if (!self::teleportBehind($victim, $attacker)) {
            return;
        }

        self::setCooldown($victim);

        PlayerUtils::playSound($victim, "mob.endermen.portal", 1.0, 1.15);
        PlayerUtils::playSound($attacker, "mob.endermen.portal", 1.0, 0.9);
    }

    private static function teleportBehind(Player $player, Player $target): bool {
        $world = $target->getWorld();

        $motion = $target->getMotion();
        $predictedX = $target->getPosition()->x + ($motion->x * 2.0);
        $predictedZ = $target->getPosition()->z + ($motion->z * 2.0);

        $direction = $target->getDirectionVector()->normalize();

        $behindX = $predictedX - ($direction->x * 1.35);
        $behindZ = $predictedZ - ($direction->z * 1.35);

        $basePos = new Vector3($behindX, $target->getPosition()->y, $behindZ);
        $safePos = self::findSafePosition($basePos, $target);

        if ($safePos === null) {
            return false;
        }

        $yaw = $target->getLocation()->getYaw();
        $pitch = 0.0;

        $player->teleport($safePos, $yaw, $pitch);
        return true;
    }

    private static function findSafePosition(Vector3 $pos, Player $ref): ?Vector3 {
        $world = $ref->getWorld();
        $x = (int) floor($pos->x);
        $z = (int) floor($pos->z);

        for ($y = (int) floor($pos->y) + 2; $y >= (int) floor($pos->y) - 3; $y--) {
            $feet = $world->getBlockAt($x, $y, $z);
            $head = $world->getBlockAt($x, $y + 1, $z);
            $ground = $world->getBlockAt($x, $y - 1, $z);

            if (!$feet->isSolid() && !$head->isSolid() && $ground->isSolid()) {
                return new Vector3($x + 0.5, $y + 0.1, $z + 0.5);
            }
        }

        $fallbackY = (int) floor($ref->getPosition()->y);
        $feet = $world->getBlockAt($x, $fallbackY, $z);
        $head = $world->getBlockAt($x, $fallbackY + 1, $z);
        $ground = $world->getBlockAt($x, $fallbackY - 1, $z);

        if (!$feet->isSolid() && !$head->isSolid() && $ground->isSolid()) {
            return new Vector3($x + 0.5, $fallbackY + 0.1, $z + 0.5);
        }

        return null;
    }

    private static function isOnCooldown(Player $player): bool {
        $key = strtolower($player->getName());
        return isset(self::$cooldowns[$key]) && self::$cooldowns[$key] > time();
    }

    private static function setCooldown(Player $player): void {
        $cooldown = max(0, EnchantTuningManager::getInt("trickster", "cooldown", self::DEFAULT_COOLDOWN));
        self::$cooldowns[strtolower($player->getName())] = time() + $cooldown;
    }

    public static function clearPlayer(Player $player): void
    {
        unset(self::$cooldowns[strtolower($player->getName())]);
    }
}