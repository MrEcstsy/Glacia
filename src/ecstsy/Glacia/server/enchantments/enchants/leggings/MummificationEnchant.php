<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\leggings;

use ecstsy\Glacia\entity\MummifiedRemnant;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ExclusiveEnchantManager;
use ecstsy\MartianUtilities\utils\ItemUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\PortalParticle;

final class MummificationEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $damager = $event->getDamager();
        if (!$victim instanceof Player || !$damager instanceof Player) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($victim);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("mummification", $level, 30);
        $result = EnchantProcManager::attempt(
            $victim,
            "mummification",
            $level,
            EnchantTuningManager::chance("mummification", $level, 7.0),
            $cooldownSeconds,
            static fn() => max(0, (int) ($session?->getCooldown("mummification") ?? 0))
        );
        if (!$result->success) {
            return;
        }

        if (!ExclusiveEnchantManager::consumeArmorUse($victim, "mummification", ArmorInventory::SLOT_LEGS)) {
            return;
        }

        $durationSeconds = max(2, EnchantTuningManager::valueInt("mummification", "duration-seconds", $level, 4));
        $health = max(12, EnchantTuningManager::valueInt("mummification", "summon-health", $level, 30));
        $damage = max(2.0, EnchantTuningManager::value("mummification", "summon-damage", $level, 5.0));
        $moveSpeed = max(0.18, EnchantTuningManager::value("mummification", "move-speed", $level, 0.27));
        $attackReach = max(1.8, EnchantTuningManager::value("mummification", "attack-reach", $level, 2.3));
        $targetRange = max(10.0, EnchantTuningManager::value("mummification", "target-range", $level, 20.0));
        $attackCooldownTicks = max(5, EnchantTuningManager::valueInt("mummification", "attack-cooldown-ticks", $level, 9));

        $armorTag = new CompoundTag();
        foreach ($victim->getArmorInventory()->getContents() as $slot => $item) {
            $encoded = self::tryEncodeItem($item);
            if ($encoded === null) {
                continue;
            }
            $armorTag->setString((string) $slot, $encoded);
        }

        $weapon = $victim->getInventory()->getItemInHand();
        $encodedWeapon = self::tryEncodeItem($weapon);
        $name = C::colorize("&r&6" . $victim->getName() . "'s Mummy");
        $position = $victim->getPosition()->add(0, 0.2, 0);
        $nbt = CompoundTag::create()
            ->setString("mummy_owner_uuid", strtolower($victim->getUniqueId()->toString()))
            ->setString("mummy_target_uuid", strtolower($damager->getUniqueId()->toString()))
            ->setString("mummy_name", $name)
            ->setInt("mummy_health", $health)
            ->setFloat("mummy_damage", $damage)
            ->setFloat("mummy_speed", $moveSpeed)
            ->setFloat("mummy_reach", $attackReach)
            ->setFloat("mummy_target_range", $targetRange)
            ->setInt("mummy_attack_cooldown", $attackCooldownTicks)
            ->setInt("mummy_lifetime_ticks", $durationSeconds * 20)
            ->setTag("mummy_armor", $armorTag);
        if ($encodedWeapon !== null) {
            $nbt->setString("mummy_weapon", $encodedWeapon);
        }

        $mummy = new MummifiedRemnant(
            new Location($position->x, $position->y, $position->z, $victim->getWorld(), lcg_value() * 360, 0),
            $nbt
        );
        $mummy->spawnToAll();

        for ($i = 0; $i < 8; $i++) {
            $victim->getWorld()->addParticle($position->add(mt_rand(-8, 8) / 10, mt_rand(2, 16) / 10, mt_rand(-8, 8) / 10), new PortalParticle());
        }
        PlayerUtils::playSound($victim, "mob.wither.spawn", 0.8, 1.2);
        PlayerUtils::playSound($damager, "mob.endermen.portal", 1.0, 0.8);

        EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
            $session?->addCooldown("mummification", $seconds);
        });
        EnchantProcManager::debugAppliedValues($victim, $result, [
            "duration_seconds" => $durationSeconds,
            "summon_health" => $health,
            "summon_damage" => $damage,
            "move_speed" => $moveSpeed,
            "attack_reach" => $attackReach,
            "target_range" => $targetRange,
        ]);
    }

    private static function tryEncodeItem(Item $item): ?string
    {
        if ($item->isNull()) {
            return null;
        }

        try {
            return ItemUtils::encodeItem($item);
        } catch (\Throwable) {
            return null;
        }
    }
}
