<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\entity\HellforgedPigman;
use ecstsy\Glacia\Loader;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\sound\AnvilUseSound;

/**
 * Nether Warden: strong pigman + victim-specific pearl lock while the minion is alive.
 */
final class NetherPigmanGuard
{
    /** @var list<array{entity: HellforgedPigman, world: string, ownerUuid: string, blockedUuid: string}> */
    private static array $minions = [];

    public static function snapshotBlocking(Player $player): bool
    {
        $playerUuid = strtolower($player->getUniqueId()->toString());
        foreach (self::$minions as $i => $row) {
            $entity = $row["entity"];
            if ($entity->isClosed() || !$entity->isAlive()) {
                unset(self::$minions[$i]);
                continue;
            }

            if (
                $row["blockedUuid"] === $playerUuid &&
                $entity->getWorld()->getFolderName() === $player->getWorld()->getFolderName()
            ) {
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
        $chance = EnchantTuningManager::getLevelInt("hellforged", "chance-by-level", $level, 5 + $level * 2);
        if (!ProcLuckManager::roll($victim, "hellforged", $chance, 2, 8)) {
            return;
        }

        $pos = $victim->getPosition()->add(((float) (mt_rand(-2, 2)) + 0.5), 0, (float) (mt_rand(-2, 2)) + 0.5);
        $location = new Location($pos->x, $pos->y, $pos->z, $victim->getWorld(), 0.0, 0.0);
        $pig = new HellforgedPigman($location, CompoundTag::create());
        $pig->setHellforgedData($victim, $attacker, "&4&lNether Warden", 20 * 30, 7.0, 80);
        $pig->spawnToAll();
        $pig->getWorld()->addSound($pig->getPosition(), new AnvilUseSound());
        $victim->sendMessage("§4Nether Warden §7summons a feral pigman. Pearls are dampened nearby.");

        self::$minions[] = [
            "entity" => $pig,
            "world" => $victim->getWorld()->getFolderName(),
            "ownerUuid" => strtolower($victim->getUniqueId()->toString()),
            "blockedUuid" => strtolower($attacker->getUniqueId()->toString()),
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
