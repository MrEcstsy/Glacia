<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class TricksterEnchant implements EntityDamageByEntityEnchantmentHandler {

    /** @var array<string, int> */
    private static array $cooldowns = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $attacker = $event->getDamager();

        if (!$victim instanceof Player || !$attacker instanceof Player) {
            return;
        }

        $key = strtolower($victim->getName());
        $chance = EnchantTuningManager::chance("trickster", $level, 4.0 + (($level - 1) * 1.0));
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("trickster", $level, 20);
        $result = EnchantProcManager::attempt(
            $victim,
            "trickster",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => max(0, (self::$cooldowns[$key] ?? 0) - time())
        );
        if (!$result->success) {
            return;
        }

        if (!self::teleportBehind($victim, $attacker, $level)) {
            return;
        }

        self::$cooldowns[$key] = time() + $cooldownSeconds;
        PlayerUtils::playSound($victim, "mob.endermen.portal", 1.0, 1.15);
        PlayerUtils::playSound($attacker, "mob.endermen.portal", 1.0, 0.9);

        EnchantProcManager::debugAppliedValues($victim, $result, [
            "target" => $attacker->getName(),
            "cooldown_seconds" => $cooldownSeconds,
        ]);
    }

    private static function teleportBehind(Player $player, Player $target, int $level): bool {
        $motionScale = max(0.0, EnchantTuningManager::value("trickster", "motion-prediction-scale", $level, 2.0));
        $behindDistance = max(0.5, EnchantTuningManager::value("trickster", "behind-distance", $level, 1.35));

        $motion = $target->getMotion();
        $predictedX = $target->getPosition()->x + ($motion->x * $motionScale);
        $predictedZ = $target->getPosition()->z + ($motion->z * $motionScale);

        $direction = $target->getDirectionVector()->normalize();

        $behindX = $predictedX - ($direction->x * $behindDistance);
        $behindZ = $predictedZ - ($direction->z * $behindDistance);

        $basePos = new Vector3($behindX, $target->getPosition()->y, $behindZ);
        $safePos = self::findSafePosition($basePos, $target);

        if ($safePos === null) {
            return false;
        }

        $player->teleport($safePos, $target->getLocation()->getYaw(), 0.0);
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

    public static function clearPlayer(Player $player): void
    {
        unset(self::$cooldowns[strtolower($player->getName())]);
    }
}
