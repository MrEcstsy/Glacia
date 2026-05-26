<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\entity\WatchmenGolem;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\items\rings\RingManager;
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
     *   expiresAt:int
     * }>
     */
    private static array $guardians = [];
    private static bool $cleanupTaskStarted = false;

    /** @var array<string, int> */
    private static array $cooldowns = [];

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
        if (RingManager::playerBlocksEnchant($attacker, "watchmen")) {
            return;
        }

        $key = strtolower($victim->getName());
        $chance = EnchantTuningManager::chance("watchmen", $level, 4.0 + ($level * 2.0));
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("watchmen", $level, 25);
        $result = EnchantProcManager::attempt(
            $victim,
            "watchmen",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => max(0, (self::$cooldowns[$key] ?? 0) - time())
        );
        if (!$result->success) {
            return;
        }

        $durationSeconds = max(3, EnchantTuningManager::valueInt("watchmen", "guardian-duration-seconds", $level, 12));
        $health = max(10, EnchantTuningManager::valueInt("watchmen", "golem-health", $level, 40));
        $damage = max(1.0, EnchantTuningManager::value("watchmen", "golem-damage", $level, 2.0 + ($level * 0.8)));
        $count = max(1, EnchantTuningManager::valueInt("watchmen", "golem-count", $level, 2));
        $spawnRadius = max(0, EnchantTuningManager::valueInt("watchmen", "spawn-radius", $level, 2));
        $displayName = "§r§e{$victim->getName()}'s Watchman";
        $lifetimeTicks = $durationSeconds * 20;

        for ($i = 0; $i < $count; $i++) {
            $offsetX = mt_rand(-$spawnRadius, $spawnRadius);
            $offsetZ = mt_rand(-$spawnRadius, $spawnRadius);
            $spawn = $victim->getPosition()->add($offsetX + 0.5, 0, $offsetZ + 0.5);
            $location = new Location($spawn->x, $spawn->y, $spawn->z, $victim->getWorld(), 0.0, 0.0);
            $golem = new WatchmenGolem($location, CompoundTag::create());
            $golem->setWatchmenData($victim, $attacker, $displayName, $lifetimeTicks, $damage, $health);
            $golem->spawnToAll();

            self::$guardians[] = [
                "golemId" => $golem->getId(),
                "world" => $victim->getWorld()->getFolderName(),
                "owner" => $victim->getName(),
                "target" => $attacker->getName(),
                "expiresAt" => time() + $durationSeconds,
            ];
        }

        self::$cooldowns[$key] = time() + $cooldownSeconds;
        self::ensureCleanupTask();

        EnchantProcManager::debugAppliedValues($victim, $result, [
            "golem_count" => $count,
            "golem_health" => $health,
            "golem_damage" => $damage,
            "duration_seconds" => $durationSeconds,
            "target" => $attacker->getName(),
        ]);
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
                !$golem instanceof WatchmenGolem ||
                $golem->isClosed() ||
                !$golem->isAlive() ||
                $owner === null ||
                !$owner->isConnected() ||
                $target === null ||
                !$target->isConnected() ||
                !$target->isAlive() ||
                $now >= (int) $entry["expiresAt"]
            ) {
                if ($golem instanceof WatchmenGolem && !$golem->isClosed()) {
                    $golem->flagForDespawn();
                }
                unset(self::$guardians[$index]);
                continue;
            }

            if ($golem->getWorld() !== $target->getWorld()) {
                $golem->flagForDespawn();
                unset(self::$guardians[$index]);
            }
        }

        self::$guardians = array_values(self::$guardians);
    }

    /**
     * @return list<WatchmenGolem>
     */
    public static function getActiveWatchmenGolems(Player $owner): array
    {
        $out = [];
        $server = Server::getInstance();
        $now = time();
        foreach (self::$guardians as $entry) {
            if (strcasecmp((string) $entry["owner"], $owner->getName()) !== 0) {
                continue;
            }
            if ($now >= (int) $entry["expiresAt"]) {
                continue;
            }
            $world = $server->getWorldManager()->getWorldByName((string) $entry["world"]);
            $golem = $world?->getEntity((int) $entry["golemId"]);
            if ($golem instanceof WatchmenGolem && !$golem->isClosed() && $golem->isAlive()) {
                $out[] = $golem;
            }
        }

        return $out;
    }
}
