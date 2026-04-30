<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;
use pocketmine\Server;

final class VirusManager
{
    /** @var array<int, array{expires: int, multiplier: float}> */
    private static array $infected = [];

    public static function infect(Living $entity, int $durationTicks, float $multiplier): void
    {
        $id = $entity->getId();
        $expires = Server::getInstance()->getTick() + max(20, $durationTicks);
        $current = self::$infected[$id] ?? null;

        self::$infected[$id] = [
            "expires" => max($expires, (int) ($current["expires"] ?? 0)),
            "multiplier" => max($multiplier, (float) ($current["multiplier"] ?? 1.0)),
        ];
    }

    public static function amplifyEffectDamage(EntityDamageEvent $event): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof Living) {
            return;
        }

        $state = self::$infected[$entity->getId()] ?? null;
        if ($state === null) {
            return;
        }

        if (!$entity->isAlive() || $state["expires"] <= Server::getInstance()->getTick()) {
            unset(self::$infected[$entity->getId()]);
            return;
        }

        if ($event instanceof EntityDamageByEntityEvent || $event->getCause() !== EntityDamageEvent::CAUSE_MAGIC) {
            return;
        }

        if ($event->getBaseDamage() > 1.01) {
            return;
        }

        $effects = $entity->getEffects();
        if (
            !$effects->has(VanillaEffects::POISON()) &&
            !$effects->has(VanillaEffects::FATAL_POISON()) &&
            !$effects->has(VanillaEffects::WITHER())
        ) {
            return;
        }

        $event->setBaseDamage($event->getBaseDamage() * $state["multiplier"]);
    }

    public static function clearEntity(Entity $entity): void
    {
        unset(self::$infected[$entity->getId()]);
    }

    public static function clearPlayer(Player $player): void
    {
        self::clearEntity($player);
    }
}
