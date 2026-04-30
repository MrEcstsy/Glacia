<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\entity\mobs\IronGolem;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

final class WatchmenEnchant implements EntityDamageByEntityEnchantmentHandler
{
    /**
     * @var array<int, array{
     *   golemId:int,
     *   world:string,
     *   owner:string,
     *   target:string,
     *   damage:float,
     *   expiresAt:int
     * }>
     */
    private static array $guardians = [];
    private static bool $cleanupTaskStarted = false;

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $attacker = $event->getDamager();
        if (!$victim instanceof Player || !$attacker instanceof Player) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($victim);
        $cooldown = max(0, EnchantTuningManager::getInt("watchmen", "cooldown", 25));
        if ($session !== null && $cooldown > 0 && $session->isOnCooldown("watchmen")) {
            return;
        }

        $chance = EnchantTuningManager::getLevelInt("watchmen", "chance-by-level", $level, 4 + ($level * 2));
        if (!ProcLuckManager::roll($victim, "watchmen", $chance, 2, 12)) {
            return;
        }

        $durationSeconds = max(3, EnchantTuningManager::getInt("watchmen", "guardian-duration-seconds", 12));
        $health = max(10, EnchantTuningManager::getInt("watchmen", "golem-health", 40));
        $damage = max(1.0, EnchantTuningManager::getLevelFloat("watchmen", "golem-damage-by-level", $level, 2.0 + ($level * 0.8)));
        $count = max(1, EnchantTuningManager::getLevelInt("watchmen", "golem-count-by-level", $level, min(3, 1 + intdiv($level, 2))));

        for ($i = 0; $i < $count; $i++) {
            $offsetX = mt_rand(-2, 2);
            $offsetZ = mt_rand(-2, 2);
            $spawn = $victim->getPosition()->add($offsetX + 0.5, 0, $offsetZ + 0.5);
            $location = new Location($spawn->x, $spawn->y, $spawn->z, $victim->getWorld(), 0.0, 0.0);
            $golem = new IronGolem($location, CompoundTag::create());
            $golem->setMaxHealth($health);
            $golem->setHealth($health);
            $golem->setNameTag("§r§e{$victim->getName()}'s Watchman");
            $golem->setNameTagVisible(true);
            $golem->setNameTagAlwaysVisible(true);
            $golem->spawnToAll();

            self::$guardians[] = [
                "golemId" => $golem->getId(),
                "world" => $victim->getWorld()->getFolderName(),
                "owner" => $victim->getName(),
                "target" => $attacker->getName(),
                "damage" => $damage,
                "expiresAt" => time() + $durationSeconds,
            ];
        }

        if ($session !== null && $cooldown > 0) {
            $session->addCooldown("watchmen", $cooldown);
        }

        self::ensureCleanupTask();
    }

    private static function ensureCleanupTask(): void
    {
        if (self::$cleanupTaskStarted) {
            return;
        }

        self::$cleanupTaskStarted = true;
        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            static function (): void {
                self::tickGuardians();
            }
        ), 20);
    }

    private static function tickGuardians(): void
    {
        if (self::$guardians === []) {
            return;
        }

        $server = Server::getInstance();
        $now = time();
        foreach (self::$guardians as $index => $entry) {
            $world = $server->getWorldManager()->getWorldByName((string) $entry["world"]);
            $owner = $server->getPlayerExact((string) $entry["owner"]);
            $target = $server->getPlayerExact((string) $entry["target"]);
            $golem = $world?->getEntity((int) $entry["golemId"]);

            if (
                $world === null ||
                !$golem instanceof IronGolem ||
                $golem->isClosed() ||
                !$golem->isAlive() ||
                $owner === null ||
                !$owner->isConnected() ||
                $target === null ||
                !$target->isConnected() ||
                !$target->isAlive() ||
                $now >= (int) $entry["expiresAt"]
            ) {
                if ($golem instanceof IronGolem && !$golem->isClosed()) {
                    $golem->flagForDespawn();
                }
                unset(self::$guardians[$index]);
                continue;
            }

            if ($golem->getWorld() !== $target->getWorld()) {
                $golem->flagForDespawn();
                unset(self::$guardians[$index]);
                continue;
            }

            $targetPos = $target->getPosition();
            if ($golem->getPosition()->distanceSquared($targetPos) > 16.0) {
                $tpX = $targetPos->x + mt_rand(-1, 1);
                $tpZ = $targetPos->z + mt_rand(-1, 1);
                $golem->teleport(new Location($tpX + 0.5, $targetPos->y, $tpZ + 0.5, $target->getWorld(), 0.0, 0.0));
            }

            if ($golem->getPosition()->distanceSquared($targetPos) <= 9.0) {
                $target->attack(new EntityDamageByEntityEvent($golem, $target, EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK, (float) $entry["damage"]));
            }
        }

        self::$guardians = array_values(self::$guardians);
    }
}
