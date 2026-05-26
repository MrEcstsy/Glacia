<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ExclusiveEnchantManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use ecstsy\Glacia\server\items\armor\ArmorSetManager;
use ecstsy\Glacia\server\items\armor\abilities\YijkiLightningStrike;
use ecstsy\Glacia\server\enchantments\enchants\sword\RageEnchant;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Sword;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\sound\GhastShootSound;

final class ArmageddonEnchant implements EntityDamageByEntityEnchantmentHandler
{
    private const ABILITY_SOUL_CRUSHER = "soul_crusher";
    private const ABILITY_DIVINE_IMMORALITY = "divine_immorality";
    private const ABILITY_NATURES_PARADOX = "natures_paradox";
    private static int $fireballBurstDepth = 0;

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($damager);
        $cooldown = EnchantTuningManager::getLevelCooldown("armageddon", $level, 30);
        if ($session !== null && $cooldown > 0 && $session->isOnCooldown("armageddon")) {
            return;
        }

        $chance = EnchantTuningManager::getLevelInt("armageddon", "chance-by-level", $level, max(1, $level));
        if (!ProcLuckManager::roll($damager, "armageddon", $chance, 1, 8)) {
            return;
        }

        if (!ExclusiveEnchantManager::consumeAnyArmorUse($damager, "armageddon")) {
            return;
        }

        $aristocraticLevel = CustomEnchantmentManager::getLevel($victim->getArmorInventory()->getBoots(), "aristocratic");
        $aristocraticActive = $aristocraticLevel > 0 && ExclusiveEnchantManager::consumeAnyArmorUse($victim, "aristocratic");

        $abilities = self::rollAbilities(
            $level,
            $aristocraticActive ? max(0, EnchantTuningManager::getInt("aristocratic", "multi-proc-reduction-percent-per-level", 20) * $aristocraticLevel) : 0,
            $aristocraticActive && $aristocraticLevel >= 5
        );

        foreach ($abilities as $ability) {
            self::applyAbility($damager, $victim, $ability, $level, $event, true, false);
        }

        if ($aristocraticActive) {
            $mirrorChance = max(0, EnchantTuningManager::getInt("aristocratic", "mirror-chance", 30));
            if ($abilities !== [] && mt_rand(1, 100) <= $mirrorChance) {
                foreach ($abilities as $ability) {
                    self::applyAbility($victim, $damager, $ability, $level, null, false, false);
                }
                $victim->sendMessage(C::colorize("&r&dAristocratic &7echoed their Armageddon back at them."));
                $damager->sendMessage(C::colorize("&r&dAristocratic &7mirrored your Armageddon proc."));
            } elseif (count($abilities) === 1) {
                $victim->sendMessage(C::colorize("&r&dAristocratic &7contained their Armageddon to a single blessing."));
            }
        }

        if ($session !== null && $cooldown > 0) {
            $session->addCooldown("armageddon", $cooldown);
        }
    }

    /**
     * Pet proc: skips cooldown/chance rolls and armor soul consume. Does not invoke Aristocratic mirroring logic.
     *
     * @return array{auraSeconds:int, abilities:list<string>}
     */
    public static function triggerPetProc(Player $damager, Player $victim, int $level): array
    {
        $abilities = self::rollAbilities($level, 0, false);
        foreach ($abilities as $ability) {
            self::applyAbility($damager, $victim, $ability, $level, null, true, true);
        }

        return [
            "auraSeconds" => self::estimatePetAuraDebuffSeconds($abilities, $level),
            "abilities" => $abilities,
        ];
    }

    /** @param list<string> $abilities */
    private static function estimatePetAuraDebuffSeconds(array $abilities, int $level): int
    {
        $max = 10;
        foreach ($abilities as $a) {
            if ($a === self::ABILITY_SOUL_CRUSHER) {
                $lock = max(1, EnchantTuningManager::getInt("armageddon", "soul-crusher-lock-seconds", 8));
                $max = max($max, $lock);
            } elseif ($a === self::ABILITY_DIVINE_IMMORALITY) {
                $max = max($max, max(1, $level));
            } elseif ($a === self::ABILITY_NATURES_PARADOX) {
                $natureSec = max(1, EnchantTuningManager::getLevelInt("armageddon", "nature-double-seconds-by-level", $level, EnchantTuningManager::getInt("armageddon", "nature-double-seconds-per-level", 2) * $level));
                $max = max($max, min(45, $natureSec + 8));
            }
        }

        return $max;
    }

    public static function applyActiveHitEffects(EntityDamageByEntityEvent $event): void
    {
        if ($event->isCancelled() || self::$fireballBurstDepth > 0) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        if (AdvancedEnchantState::hasSoulCrusherHits($damager)) {
            $stack = max(1, EnchantTuningManager::getInt("armageddon", "soul-crusher-rage-stack", 10));
            $damagePerStack = EnchantTuningManager::getFloat("rage", "damage-per-stack-half-hearts", 0.5);
            AdvancedEnchantState::markSoulCrusherCurrentHit($damager);
            $event->setBaseDamage($event->getBaseDamage() + ($stack * $damagePerStack));
            AdvancedEnchantState::consumeSoulCrusherHit($damager);
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                static function () use ($damager): void {
                    AdvancedEnchantState::consumeSoulCrusherCurrentHit($damager);
                }
            ), 1);
            if (!AdvancedEnchantState::hasSoulCrusherHits($damager)) {
                RageEnchant::reset($damager->getName());
            }
        }

        if (AdvancedEnchantState::hasDivineFireball($damager)) {
            $radius = max(1.0, EnchantTuningManager::getFloat("armageddon", "divine-fireball-radius", 5.0));
            $damage = max(1.0, EnchantTuningManager::getFloat("armageddon", "divine-fireball-damage", 6.0));
            self::burstFireball($damager, $victim, $damage, $radius, false);
        }
    }

    /**
     * @return list<string>
     */
    private static function rollAbilities(int $level, int $extraReductionPercent = 0, bool $forceSingle = false): array
    {
        $pool = [
            self::ABILITY_SOUL_CRUSHER,
            self::ABILITY_DIVINE_IMMORALITY,
            self::ABILITY_NATURES_PARADOX,
        ];
        shuffle($pool);

        $selected = [array_shift($pool)];
        if ($forceSingle) {
            return array_values(array_filter($selected));
        }

        $extraChance = EnchantTuningManager::getLevelInt(
            "armageddon",
            "extra-ability-chance-by-level",
            $level,
            min(35, 4 + ($level * 3))
        );
        $extraChance = max(0, min(100, $extraChance - $extraReductionPercent));

        foreach ($pool as $ability) {
            if (mt_rand(1, 100) <= $extraChance) {
                $selected[] = $ability;
            }
        }

        return array_values(array_unique(array_filter($selected)));
    }

    private static function applyAbility(
        Player $owner,
        Player $target,
        string $ability,
        int $level,
        ?EntityDamageByEntityEvent $event,
        bool $allowSetBoost,
        bool $fromPetTrigger = false
    ): void {
        switch ($ability) {
            case self::ABILITY_SOUL_CRUSHER:
                $hits = max(1, EnchantTuningManager::getInt("armageddon", "soul-crusher-hits", 5));
                $lockSeconds = max(1, EnchantTuningManager::getInt("armageddon", "soul-crusher-lock-seconds", 8));
                AdvancedEnchantState::setSoulCrusherHits($owner, $hits);
                AdvancedEnchantState::lockInventory($target, $lockSeconds);
                GlaciaUtils::repairAll($owner);
                $owner->sendMessage(C::colorize("&r&6Soul Crusher &7primed your next {$hits} hits."));
                $target->sendMessage(C::colorize("&r&cSoul Crusher &7locked your inventory and hotbar."));
                break;

            case self::ABILITY_DIVINE_IMMORALITY:
                $seconds = max(1, $level);
                $owner->getEffects()->add(new EffectInstance(VanillaEffects::INVISIBILITY(), $seconds * 20, 0, false, false));
                AdvancedEnchantState::setDivineFireball($owner, $seconds);
                self::blindNearbyEnemyNametags($owner, $seconds);
                if ($event !== null || $fromPetTrigger) {
                    $radius = max(1.0, EnchantTuningManager::getFloat("armageddon", "divine-fireball-radius", 5.0));
                    $damage = max(1.0, EnchantTuningManager::getFloat("armageddon", "divine-fireball-immediate-damage", 4.0));
                    self::burstFireball($owner, $target, $damage, $radius, true);
                }
                $owner->sendMessage(C::colorize("&r&5Divine Immorality &7wrapped you in mythic flame."));
                break;

            case self::ABILITY_NATURES_PARADOX:
                $target->getHungerManager()->setFood(0);
                $target->getHungerManager()->setSaturation(0.0);
                self::triggerNatureParadox($owner, $target, $level, $event, $allowSetBoost);
                break;
        }
    }

    private static function blindNearbyEnemyNametags(Player $owner, int $seconds): void
    {
        $radius = max(1.0, EnchantTuningManager::getFloat("armageddon", "divine-radius", 3.0));
        $center = $owner->getPosition();
        foreach ($owner->getWorld()->getPlayers() as $player) {
            if ($player->getId() === $owner->getId()) {
                continue;
            }
            if ($player->getPosition()->distanceSquared($center) > ($radius * $radius)) {
                continue;
            }
            if (GlaciaUtils::isAlly($owner, $player)) {
                continue;
            }
            AdvancedEnchantState::blindNametags($player, $seconds);
        }

        GlaciaUtils::refreshAllOnlineNametags();
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function (): void {
                GlaciaUtils::refreshAllOnlineNametags();
            }
        ), ($seconds * 20) + 1);
    }

    private static function burstFireball(Player $owner, Player $centerTarget, float $damage, float $radius, bool $lighterBurst): void
    {
        $center = $centerTarget->getPosition()->add(0, 1.0, 0);
        $world = $centerTarget->getWorld();

        $world->addSound($center, new GhastShootSound());
        $particleBursts = $lighterBurst ? 7 : 11;
        for ($i = 0; $i < $particleBursts; $i++) {
            $particlePos = $center->add(mt_rand(-9, 9) / 10, mt_rand(-2, 6) / 10, mt_rand(-9, 9) / 10);
            $world->addParticle($particlePos, new FlameParticle());
            $world->addParticle($particlePos, new PortalParticle());
        }

        $bb = new AxisAlignedBB(
            $center->x - $radius,
            $center->y - $radius,
            $center->z - $radius,
            $center->x + $radius,
            $center->y + $radius,
            $center->z + $radius
        );

        self::$fireballBurstDepth++;
        try {
            foreach ($world->getNearbyEntities($bb) as $entity) {
                if (!$entity instanceof Player || !$entity->isAlive()) {
                    continue;
                }
                if ($entity->getId() === $owner->getId()) {
                    continue;
                }
                if (GlaciaUtils::isAlly($owner, $entity)) {
                    continue;
                }
                if ($entity->getPosition()->distanceSquared($center) > ($radius * $radius)) {
                    continue;
                }

                $entity->attack(new EntityDamageByEntityEvent(
                    $owner,
                    $entity,
                    EntityDamageEvent::CAUSE_MAGIC,
                    $damage
                ));
            }
        } finally {
            self::$fireballBurstDepth = max(0, self::$fireballBurstDepth - 1);
        }
    }

    private static function triggerNatureParadox(
        Player $owner,
        Player $target,
        int $level,
        ?EntityDamageByEntityEvent $event,
        bool $allowSetBoost
    ): void {
        $rolledSet = ["phantom", "yeti", "yijki"][array_rand(["phantom", "yeti", "yijki"])];
        $currentSet = ArmorSetManager::getInstance()->getFullSet($owner);
        $boostMultiplier = 1.0;

        if ($allowSetBoost && $currentSet === $rolledSet) {
            $seconds = max(1, EnchantTuningManager::getLevelInt("armageddon", "nature-double-seconds-by-level", $level, EnchantTuningManager::getInt("armageddon", "nature-double-seconds-per-level", 2) * $level));
            $boostMultiplier = 2.0;
            AdvancedEnchantState::setNatureParadoxBoost($owner, $rolledSet, $seconds, $boostMultiplier);
        }

        switch ($rolledSet) {
            case "phantom":
                if ($event !== null) {
                    $event->setBaseDamage($event->getBaseDamage() * (1.0 + (0.15 * $boostMultiplier)));
                }
                $owner->sendMessage(C::colorize("&r&5Nature's Paradox &7called on the Dreadveil."));
                break;

            case "yeti":
                if ($event !== null) {
                    $event->setBaseDamage($event->getBaseDamage() * (1.0 + (0.10 * $boostMultiplier)));
                }
                if (self::isUsingGlaciaItem($owner, "yeti_sword")) {
                    self::damageArmor($target, (int) round($boostMultiplier));
                }
                $owner->sendMessage(C::colorize("&r&bNature's Paradox &7called on the Frostbound."));
                break;

            case "yijki":
                $strikeDamage = max(1.0, EnchantTuningManager::getFloat("armageddon", "nature-lightning-damage", 4.0) * $boostMultiplier);
                YijkiLightningStrike::strike($owner, $target->getPosition(), $strikeDamage, 3.0);
                if ($event !== null && self::isUsingGlaciaItem($owner, "yijki_sword")) {
                    $event->setBaseDamage($event->getBaseDamage() * (1.0 + (0.10 * $boostMultiplier)));
                }
                $owner->sendMessage(C::colorize("&r&fNature's Paradox &7called on the Stormcaller."));
                break;
        }
    }

    private static function damageArmor(Player $victim, int $amount): void
    {
        $armor = $victim->getArmorInventory();
        foreach ($armor->getContents() as $slot => $item) {
            if (!$item instanceof \pocketmine\item\Durable || $item->isNull()) {
                continue;
            }
            $item->applyDamage(max(1, $amount));
            $armor->setItem($slot, $item);
        }
    }

    private static function isUsingGlaciaItem(Player $player, string $identifier): bool
    {
        $item = $player->getInventory()->getItemInHand();
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return $glacia !== null && $glacia->getString("glaciaItem", "") === $identifier;
    }
}
