<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\entity\envoys\EnvoyZombiePigman;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\sound\AnvilUseSound;

/**
 * Nether Warden: strong pigman + enderpearl block in a 5 block radius of the minion.
 */
final class NetherPigmanGuard
{
    private const RADIUS = 5.0;

    /** @var list<array{entity: EnvoyZombiePigman, world: string, ownerName: string}> */
    private static array $minions = [];

    public static function snapshotBlocking(Player $inWorld): bool
    {
        foreach (self::$minions as $i => $row) {
            $e = $row["entity"];
            if ($e->isClosed() || !$e->isAlive()) {
                unset(self::$minions[$i]);
                continue;
            }
            if ($e->getWorld()->getFolderName() !== $inWorld->getWorld()->getFolderName()) {
                continue;
            }
            if ($e->getPosition()->distanceSquared($inWorld->getPosition()) <= (self::RADIUS * self::RADIUS)) {
                return true;
            }
        }
        self::$minions = array_values(self::$minions);
        return false;
    }

    public static function trySpawnAndGuardNetherWarden(
        Player $victim,
        Player $attacker,
        int $level
    ): void {
        $chance = EnchantTuningManager::getLevelInt("netherwarden", "chance-by-level", $level, 5 + $level * 2);
        if (!ProcLuckManager::roll($victim, "netherwarden", $chance, 2, 8)) {
            return;
        }

        $pos = $victim->getPosition()->add(((float) (mt_rand(-2, 2)) + 0.5), 0, (float) (mt_rand(-2, 2)) + 0.5);
        $location = new Location($pos->x, $pos->y, $pos->z, $victim->getWorld(), 0.0, 0.0);
        $pig = new EnvoyZombiePigman($location, CompoundTag::create());
        $pig->setMaxHealth(80);
        $pig->setHealth(80.0);
        $pig->setNameTag("§4§lNether Warden");
        $pig->setNameTagVisible(true);
        $pig->setNameTagAlwaysVisible(true);
        $pig->spawnToAll();
        $pig->getWorld()->addSound($pig->getPosition(), new AnvilUseSound());
        $victim->sendMessage("§4Nether Warden §7summons a feral pigman. Pearls are dampened nearby.");

        self::$minions[] = [
            "entity" => $pig,
            "world" => $victim->getWorld()->getFolderName(),
            "ownerName" => $victim->getName(),
        ];

        $id = $pig->getId();
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(
            new ClosureTask(static function () use ($id): void {
                foreach (self::$minions as $i => $row) {
                    if ($row["entity"]->getId() === $id) {
                        if (!$row["entity"]->isClosed()) {
                            $row["entity"]->close();
                        }
                        unset(self::$minions[$i]);
                        self::$minions = array_values(self::$minions);
                        break;
                    }
                }
            }),
            20 * 30
        );
    }
}
