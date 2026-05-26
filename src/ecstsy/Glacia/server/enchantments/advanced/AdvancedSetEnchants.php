<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\advanced;

use ecstsy\Glacia\entity\GlaciaExplosiveCreeper;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\discord\DiscordLinkManager;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\enchants\sword\RageEnchant;
use ecstsy\Glacia\server\enchantments\Groups;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\BleedManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\NetherPigmanGuard;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use ecstsy\Glacia\server\items\armor\HeroicArmorDurabilityManager;
use ecstsy\Glacia\server\items\armor\ArmorSetManager;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\items\rings\RingManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\entity\Location;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Durable;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AdvancedSetEnchants
{
    public static function route(
        string $id,
        EntityDamageByEntityEvent $event,
        $enchant,
        int $level
    ): void {
        if ($event->isCancelled()) {
            return;
        }
        match ($id) {
            "wrathbreak" => self::furylash($event, $level),
            "nullglimmer" => self::glimmerfade($event, $level),
            "premonition" => self::forewarned($event, $level),
            "hexproof" => self::maliceward($event, $level),
            "rotbrand" => self::vilewound($event, $level),
            "finalverdict" => self::coupdegrace($event, $level),
            "choirglass" => self::harmonicSurge($event, $level),
            "warband" => self::phalanx($event, $level),
            "tempestomen" => self::stormrite($event, $level),
            "hellforged" => self::netherWarden($event, $level),
            "sunveil" => self::auroraWard($event, $level),
            "beastbond" => self::primalPact($event, $level),
            "severance" => self::discordVerdict($event, $level),
            "alembic" => self::alchemistEdge($event, $level),
            "marauder" => self::supplyScour($event, $level),
            "thirdeye" => self::trifoldRend($event, $level),
            "lockjaw" => self::ironMuzzle($event, $level),
            default => null,
        };
    }

    public static function onPreProcessDamage(EntityDamageByEntityEvent $event): void
    {
        if ($event->isCancelled()) {
            return;
        }
        $victim = $event->getEntity();
        $damager = $event->getDamager();
        if (!$victim instanceof Player) {
            return;
        }
        if (AdvancedEnchantState::consumeForewarnedHalve($victim)) {
            $event->setBaseDamage($event->getBaseDamage() * 0.5);
        }
        if ($damager instanceof Player) {
            $helD = $damager->getArmorInventory()->getHelmet();
            if (CustomEnchantmentManager::getLevel($helD, "triarchsigil") >= 4) {
                $selfPenaltyMultiplier = max(0.1, min(1.0, EnchantTuningManager::getFloat("triarchsigil", "attacker-self-damage-multiplier", 0.9)));
                $event->setBaseDamage($event->getBaseDamage() * $selfPenaltyMultiplier);
            }
            $helV = $victim->getArmorInventory()->getHelmet();
            if (!($helV->isNull())) {
                $ht = CustomEnchantmentManager::getLevel($helV, "lifetide");
                if ($ht > 0) {
                    $max = $victim->getMaxHealth();
                    $healthFraction = $max > 0 ? $victim->getHealth() / $max : 0.5;
                    $healthBalance = ($healthFraction - 0.5) * 2.0;
                    $defaultSwing = 0.03 + (0.01 * max(0, $ht - 1));
                    $swing = max(0.0, EnchantTuningManager::getLevelFloat("lifetide", "health-swing-by-level", $ht, $defaultSwing));
                    $minMultiplier = max(0.1, EnchantTuningManager::getLevelFloat("lifetide", "min-damage-multiplier-by-level", $ht, max(0.1, 1.0 - $defaultSwing)));
                    $maxMultiplier = max($minMultiplier, EnchantTuningManager::getLevelFloat("lifetide", "max-damage-multiplier-by-level", $ht, 1.0 + $defaultSwing));
                    $multiplier = 1.0 + ($swing * $healthBalance);
                    $multiplier = max($minMultiplier, min($maxMultiplier, $multiplier));
                    $event->setBaseDamage($event->getBaseDamage() * $multiplier);
                }
                $tw = CustomEnchantmentManager::getLevel($helV, "triarchsigil");
                if ($tw > 0) {
                    $set = ArmorSetManager::getInstance()->getFullSet($damager);
                    $protectedSets = EnchantTuningManager::getStringList("triarchsigil", "protected-against-sets");
                    if ($protectedSets === []) {
                        $protectedSets = ["phantom", "yijki", "yeti"];
                    }
                    $normalizedSet = is_string($set) ? strtolower($set) : "";
                    if ($normalizedSet !== "" && in_array($normalizedSet, array_map('strtolower', $protectedSets), true)) {
                        $reductionPerLevel = max(0.0, EnchantTuningManager::getFloat("triarchsigil", "set-damage-reduction-per-level", 0.03));
                        $reductionCap = max(0.0, min(0.95, EnchantTuningManager::getFloat("triarchsigil", "set-damage-reduction-cap", 0.12)));
                        $red = min($reductionCap, $reductionPerLevel * $tw);
                        $event->setBaseDamage($event->getBaseDamage() * (1.0 - $red));
                    }
                }
            }
            $b = $victim->getArmorInventory()->getBoots();
            if (!$b->isNull()) {
                $aa = CustomEnchantmentManager::getLevel($b, "oathbound");
                if ($aa > 0) {
                    $n = count(CustomEnchantmentManager::getEnchantments($damager->getInventory()->getItemInHand()));
                    $weaponEnchantCap = max(1, EnchantTuningManager::getInt("oathbound", "weapon-enchant-cap", 30));
                    $n = min($weaponEnchantCap, $n);
                    $damageReductionPerEnchant = max(0.0, EnchantTuningManager::getFloat("oathbound", "damage-reduction-per-enchant-per-level", 0.01));
                    $damageReductionCap = max(0.0, EnchantTuningManager::getFloat("oathbound", "damage-reduction-cap", 0.45));
                    $minimumMultiplier = max(0.05, EnchantTuningManager::getFloat("oathbound", "minimum-damage-multiplier", 0.2));
                    $allyRadius = max(0.5, EnchantTuningManager::getFloat("oathbound", "ally-radius", 3.5));
                    $m = 1.0 - min($damageReductionCap, $damageReductionPerEnchant * $aa * $n);
                    if ($damager->getPosition()->distanceSquared($victim->getPosition()) <= $allyRadius * $allyRadius) {
                        $event->setBaseDamage($event->getBaseDamage() * max($minimumMultiplier, $m));
                    } else {
                        $vp = $victim->getPosition();
                        foreach ($victim->getWorld()->getPlayers() as $ally) {
                            if ($ally === $victim) {
                                continue;
                            }
                            if (!GlaciaUtils::isAlly($victim, $ally)) {
                                continue;
                            }
                            if ($ally->getPosition()->distanceSquared($vp) > $allyRadius * $allyRadius) {
                                continue;
                            }
                            if ($damager->getPosition()->distanceSquared($vp) <= $allyRadius * $allyRadius) {
                                $event->setBaseDamage($event->getBaseDamage() * max($minimumMultiplier, $m));
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    public static function hasSteadfastSword(Player $p): bool
    {
        return CustomEnchantmentManager::getLevel($p->getInventory()->getItemInHand(), "bloodrush") > 0;
    }

    // ---

    private static function furylash(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        $d = $e->getDamager();
        if (!$v instanceof Player || !$d instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getChestplate(), "wrathbreak") < 1) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("wrathbreak", "chance-by-level", $l, min(100, $l));
        if (!ProcLuckManager::roll($v, "wrathbreak", $chance, 1, 6)) {
            return;
        }
        RageEnchant::reset($d->getName());
        $d->sendMessage(C::colorize("&r&cFurylash &7reset your Rage stack."));
    }

    private static function glimmerfade(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        $d = $e->getDamager();
        if (!$v instanceof Player || !$d instanceof Player) {
            return;
        }
        if (RingManager::playerBlocksEnchant($v, "nullglimmer")) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getChestplate(), "nullglimmer") < 1) {
            return;
        }
        $session = Loader::getPlayerManager()->getSession($v);
        if (($session?->getCooldown("nullglimmer") ?? 0) > 0) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("nullglimmer", "chance-by-level", $l, 2 + 2 * $l);
        if (!ProcLuckManager::roll($v, "nullglimmer", $chance, 2, 6)) {
            return;
        }
        $seconds = max(1, EnchantTuningManager::getLevelInt("nullglimmer", "suppress-seconds-by-level", $l, 2 * $l));
        AdvancedEnchantState::setGlimmerSuppressed($d, $seconds, 1);
        $session?->addCooldown("nullglimmer", EnchantTuningManager::getLevelCooldown("nullglimmer", $l, 8));
    }

    private static function forewarned(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        if (!$v instanceof Player) {
            return;
        }
        if (RingManager::playerBlocksEnchant($v, "premonition")) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getChestplate(), "premonition") < 1) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("premonition", "chance-by-level", $l, 4 + 3 * $l);
        if (!ProcLuckManager::roll($v, "premonition", $chance, 1, 5)) {
            return;
        }
        AdvancedEnchantState::setForewarnedNextHitHalved($v);
    }

    private static function maliceward(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        if (!$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getChestplate(), "hexproof") < 1) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("hexproof", "chance-by-level", $l, 2 + 2 * $l);
        if (!ProcLuckManager::roll($v, "hexproof", $chance, 1, 6)) {
            return;
        }
        $seconds = max(1, EnchantTuningManager::getLevelInt("hexproof", "immunity-seconds-by-level", $l, 5 * $l));
        AdvancedEnchantState::setMalicewardImmune($v, $seconds, 1);
    }

    private static function vilewound(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        $d = $e->getDamager();
        if (!$v instanceof Player || !$d instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getChestplate(), "rotbrand") < 1) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("rotbrand", "chance-by-level", $l, 2 + 2 * $l);
        if (!ProcLuckManager::roll($v, "rotbrand", $chance, 1, 5)) {
            return;
        }
        BleedManager::apply($d, $l);
    }

    private static function coupdegrace(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        $d = $e->getDamager();
        if (!$v instanceof Player || !$d instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getLeggings(), "finalverdict") < 1) {
            return;
        }
        $session = Loader::getPlayerManager()->getSession($v);
        if (($session?->getCooldown("finalverdict") ?? 0) > 0) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("finalverdict", "chance-by-level", $l, 5);
        if (!ProcLuckManager::roll($v, "finalverdict", $chance, 1, 4)) {
            return;
        }
        $victimThreshold = max(0.0, EnchantTuningManager::getLevelFloat("finalverdict", "victim-health-threshold-by-level", $l, 10.0));
        $attackerThreshold = max(0.0, EnchantTuningManager::getLevelFloat("finalverdict", "attacker-health-threshold-by-level", $l, 10.0));
        $invulnSeconds = max(1, EnchantTuningManager::getLevelInt("finalverdict", "invulnerability-seconds-by-level", $l, 2));
        $silenceSeconds = max(1, EnchantTuningManager::getLevelInt("finalverdict", "attacker-silence-seconds-by-level", $l, 7));
        $cooldownSeconds = max(1, EnchantTuningManager::getLevelInt("finalverdict", "cooldown-seconds-by-level", $l, 12));
        if ($v->getHealth() <= $victimThreshold) {
            AdvancedEnchantState::setCoupInvuln($v, $invulnSeconds);
        }
        if ($d->getHealth() <= $attackerThreshold) {
            AdvancedEnchantState::setCoupAttackerSilence($d, $silenceSeconds);
        }
        $session?->addCooldown("finalverdict", $cooldownSeconds);
    }

    private static function harmonicSurge(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        if (!$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getLeggings(), "choirglass") < 1) {
            return;
        }
        $session = Loader::getPlayerManager()->getSession($v);
        if (($session?->getCooldown("choirglass") ?? 0) > 0) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("choirglass", "chance-by-level", $l, $l);
        if (!ProcLuckManager::roll($v, "choirglass", $chance, 1, 3)) {
            return;
        }
        $cands = [];
        foreach ($v->getArmorInventory()->getContents() as $part) {
            if ($part->isNull()) {
                continue;
            }
            foreach (CustomEnchantmentManager::getEnchantments($part) as $k => $lv) {
                if (strtolower($k) === "choirglass") {
                    continue;
                }
                $ce = CustomEnchantments::getEnchantmentByName($k);
                if ($ce === null) {
                    continue;
                }
                if ($ce->getRarity() === Groups::getGroupId("MASTERY")) {
                    $cands[] = true;
                }
            }
        }
        if ($cands !== []) {
            $heal = max(0.0, EnchantTuningManager::getLevelFloat("choirglass", "heal-by-level", $l, 8.0));
            if (!AdvancedEnchantState::isAnatomyBlocked($v)) {
                $v->setHealth(min($v->getMaxHealth(), $v->getHealth() + $heal));
            }
        }
        $session?->addCooldown("choirglass", EnchantTuningManager::getLevelCooldown("choirglass", $l, 10));
    }

    private static function phalanx(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        if (!$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getLeggings(), "warband") < 1) {
            return;
        }
        $attackerWindow = max(1, EnchantTuningManager::getLevelInt("warband", "attacker-window-seconds-by-level", $l, 8));
        if ($e->getDamager() instanceof Player) {
            EnchantCombatStateManager::registerIncomingAttacker($v, $e->getDamager(), $attackerWindow);
        }
        $n = EnchantCombatStateManager::countRecentAttackers($v);
        $minAttackers = max(1, EnchantTuningManager::getLevelInt("warband", "minimum-attackers-by-level", $l, 1));
        if ($n < $minAttackers) {
            $n = $minAttackers;
        }
        $maxAmplifier = max(0, EnchantTuningManager::getLevelInt("warband", "max-regeneration-amplifier-by-level", $l, 3));
        $regenSeconds = max(1, EnchantTuningManager::getLevelInt("warband", "regeneration-seconds-by-level", $l, 2));
        $amp = (int) min($maxAmplifier, max(0, $n - 1));
        $v->getEffects()->add(new EffectInstance(VanillaEffects::REGENERATION(), 20 * $regenSeconds, $amp, false, false));
    }

    private static function stormrite(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        $d = $e->getDamager();
        if (!$v instanceof Player || !$d instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getLeggings(), "tempestomen") < 1) {
            return;
        }
        $session = PlayerManager::getInstance()->getSession($v);
        $cooldown = EnchantTuningManager::getLevelCooldown("tempestomen", $l, 45);
        if ($session !== null && $cooldown > 0 && $session->isOnCooldown("tempestomen")) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("tempestomen", "chance-by-level", $l, 1 + 2 * $l);
        if (!ProcLuckManager::roll($v, "tempestomen", $chance, 1, 3)) {
            return;
        }
        $yawRad = deg2rad($d->getLocation()->yaw);
        $spawnPos = $d->getPosition()->add(-sin($yawRad) * 1.8, 0.6, cos($yawRad) * 1.8);
        $location = new Location(
            $spawnPos->x + 0.5,
            $spawnPos->y,
            $spawnPos->z + 0.5,
            $d->getWorld(),
            $d->getLocation()->yaw,
            $d->getLocation()->pitch
        );
        $creeper = new GlaciaExplosiveCreeper($location);
        $creeper->setExplosiveType(CustomTNTIds::GIGANTIC);
        $creeper->setOwnerUuid($v->getUniqueId()->toString());
        $creeper->setBlockBreakingEnabled(false);
        $scale = max(0.1, EnchantTuningManager::getLevelFloat("tempestomen", "tempest-scale-by-level", $l, 1.1));
        $creeper->setScale($scale);
        $displayName = EnchantTuningManager::getString("tempestomen", "tempest-display-name", "&r&b&lTempest Charge");
        $creeper->setCustomDisplayName(C::colorize($displayName));
        $damage = max(0.0, EnchantTuningManager::getLevelFloat("tempestomen", "tempest-damage-by-level", $l, 4.5 + ($l * 1.25)));
        $damageRadius = max(1.5, EnchantTuningManager::getLevelFloat("tempestomen", "tempest-damage-radius-by-level", $l, 3.4));
        $triggerRadius = max(1.0, EnchantTuningManager::getLevelFloat("tempestomen", "tempest-trigger-radius-by-level", $l, 2.35));
        $chaseSpeed = max(0.18, EnchantTuningManager::getLevelFloat("tempestomen", "tempest-chase-speed-by-level", $l, 0.48 + (0.02 * min(5, $l))));
        $maxChaseTicks = max(20, EnchantTuningManager::getLevelInt("tempestomen", "tempest-max-chase-ticks-by-level", $l, 1200));
        $armTicks = max(0, EnchantTuningManager::getLevelInt("tempestomen", "tempest-arm-ticks-by-level", $l, 12));
        $creeper->configureTempestCharge(
            $d->getUniqueId()->toString(),
            $damage,
            $damageRadius,
            $triggerRadius,
            $chaseSpeed,
            $maxChaseTicks,
            $armTicks,
            $d->getId(),
            $d->getName()
        );
        $creeper->spawnToAll();
        $creeper->playTempestChargeSpawnEffects();
        $blockS = max(1, EnchantTuningManager::getLevelInt("tempestomen", "gapple-block-seconds-by-level", $l, EnchantTuningManager::getInt("tempestomen", "gapple-block-seconds", 5)));
        PetManager::blockGapples($d, $blockS);
        $armorPercent = max(0.0, EnchantTuningManager::getLevelFloat("tempestomen", "armor-damage-percent-by-level", $l, 0.05));
        $arm = $d->getArmorInventory();
        foreach ($arm->getContents() as $s => $it) {
            if ($it instanceof Durable && !$it->isNull()) {
                $dd = (int) ceil($it->getMaxDurability() * $armorPercent);
                HeroicArmorDurabilityManager::applyVirtualDurabilityDamage($it, $dd);
                $arm->setItem($s, $it);
            }
        }
        if ($session !== null && $cooldown > 0) {
            $session->addCooldown("tempestomen", $cooldown);
        }
    }

    private static function netherWarden(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        $d = $e->getDamager();
        if (!$v instanceof Player || !$d instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getBoots(), "hellforged") < 1) {
            return;
        }
        NetherPigmanGuard::trySpawnAndGuardNetherWarden($v, $d, $l);
    }

    private static function auroraWard(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        if (!$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getBoots(), "sunveil") < 1) {
            return;
        }
        $session = Loader::getPlayerManager()->getSession($v);
        if (($session?->getCooldown("sunveil") ?? 0) > 0) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("sunveil", "chance-by-level", $l, 3 + 2 * $l);
        if (!ProcLuckManager::roll($v, "sunveil", $chance, 1, 4)) {
            return;
        }
        $dayMultiplier = max(0.0, EnchantTuningManager::getFloat("sunveil", "day-heal-multiplier", 2.0));
        $nightMultiplier = max(0.0, EnchantTuningManager::getFloat("sunveil", "night-heal-multiplier", 1.0));
        $time = (int) ($v->getWorld()->getTime() % 24000);
        $isNightWindow = $time >= 13000 && $time <= 23000;
        $m = $isNightWindow ? $nightMultiplier : $dayMultiplier;
        $h = max(0.0, EnchantTuningManager::getLevelFloat("sunveil", "heal-by-level", $l, 0.5 * $l)) * (float) $m;
        $radius = max(1.0, EnchantTuningManager::getLevelFloat("sunveil", "radius-by-level", $l, 9.0));
        $p = $v->getPosition();
        foreach ($v->getWorld()->getPlayers() as $pl) {
            if ($pl->getPosition()->distanceSquared($p) > $radius * $radius) {
                continue;
            }
            if ($pl !== $v && !GlaciaUtils::isAlly($v, $pl)) {
                continue;
            }
            if (AdvancedEnchantState::isAnatomyBlocked($pl)) {
                continue;
            }
            $pl->setHealth(min($pl->getMaxHealth(), $pl->getHealth() + $h));
        }
        $session?->addCooldown("sunveil", EnchantTuningManager::getLevelCooldown("sunveil", $l, 8));
    }

    private static function primalPact(EntityDamageByEntityEvent $e, int $l): void
    {
        $d = $e->getDamager();
        if (!$d instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "beastbond") < 1) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("beastbond", "chance-by-level", $l, 3 + 2 * $l);
        if (!ProcLuckManager::roll($d, "beastbond", $chance, 1, 5)) {
            return;
        }
        $pactS = max(1, EnchantTuningManager::getLevelInt("beastbond", "pact-active-seconds-by-level", $l, EnchantTuningManager::getInt("beastbond", "pact-active-seconds", 10)));
        AdvancedEnchantState::setPrimalPactWindow($d, $pactS, $l);
    }

    private static function discordVerdict(EntityDamageByEntityEvent $e, int $l): void
    {
        $d = $e->getDamager();
        $v = $e->getEntity();
        if (!$d instanceof Player || !$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "severance") < 1) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("severance", "chance-by-level", $l, 5);
        if (!ProcLuckManager::roll($d, "severance", $chance, 1, 3)) {
            return;
        }
        $s = PlayerManager::getInstance()->getSession($v);
        $linked = $s !== null && DiscordLinkManager::isLinked($s);
        $f = max(1, EnchantTuningManager::getLevelInt("severance", "linked-slowness-ticks-by-level", $l, 20));
        if (!$linked) {
            $f = max(1, EnchantTuningManager::getLevelInt("severance", "unlinked-slowness-ticks-by-level", $l, 3 * 20));
            $magicDamageMultiplier = max(0.0, EnchantTuningManager::getLevelFloat("severance", "unlinked-magic-damage-multiplier-by-level", $l, 1.0));
            $v->attack(new EntityDamageEvent($v, EntityDamageEvent::CAUSE_MAGIC, $e->getFinalDamage() * $magicDamageMultiplier));
        }
        $slownessAmplifier = max(0, EnchantTuningManager::getLevelInt("severance", "slowness-amplifier-by-level", $l, 4));
        $v->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), $f, $slownessAmplifier));
    }

    private static function alchemistEdge(EntityDamageByEntityEvent $e, int $l): void
    {
        $d = $e->getDamager();
        $v = $e->getEntity();
        if (!$d instanceof Player || !$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "alembic") < 1) {
            return;
        }
        $chance = max(0, min(100, EnchantTuningManager::getLevelInt("alembic", "chance-by-level", $l, 100)));
        if (!ProcLuckManager::roll($d, "alembic", $chance, 1, 4)) {
            return;
        }
        $pots = 0;
        foreach ($v->getInventory()->getContents() as $it) {
            $typeId = $it->getTypeId();
            if ($typeId === ItemTypeIds::POTION || $typeId === ItemTypeIds::SPLASH_POTION || $typeId === ItemTypeIds::LINGERING_POTION) {
                $pots += $it->getCount();
            }
        }
        $noPotionMultiplier = max(0.0, EnchantTuningManager::getLevelFloat("alembic", "no-potion-damage-multiplier-by-level", $l, 0.75));
        $potionStep = max(1, EnchantTuningManager::getLevelInt("alembic", "potion-step-by-level", $l, 6));
        $bonusDamagePerStep = max(0.0, EnchantTuningManager::getLevelFloat("alembic", "bonus-damage-per-step-by-level", $l, 0.75));
        if ($pots === 0) {
            $e->setBaseDamage($e->getBaseDamage() * $noPotionMultiplier);
        } else {
            $b = intdiv($pots, $potionStep);
            if ($b > 0) {
                $e->setBaseDamage($e->getBaseDamage() + $bonusDamagePerStep * $b);
            }
        }
    }

    private static function supplyScour(EntityDamageByEntityEvent $e, int $l): void
    {
        $d = $e->getDamager();
        $v = $e->getEntity();
        if (!$d instanceof Player || !$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "marauder") < 1) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("marauder", "chance-by-level", $l, 1 + $l);
        if (!ProcLuckManager::roll($d, "marauder", $chance, 1, 6)) {
            return;
        }
        $stealOrder = EnchantTuningManager::getStringList("marauder", "steal-order");
        if ($stealOrder === []) {
            $stealOrder = ["ender_pearl", "enchanted_golden_apple", "golden_apple", "splash_potion", "lingering_potion", "potion"];
        }

        $available = [];
        foreach ($stealOrder as $candidate) {
            $normalized = strtolower($candidate);
            if (self::hasSupplyScourCandidate($v, $normalized)) {
                $available[] = $normalized;
            }
        }

        if ($available === []) {
            return;
        }

        $first = $available[array_rand($available)];
        $attempts = array_values(array_unique(array_merge([$first], $available)));
        foreach ($attempts as $candidate) {
            if (self::trySupplyScourSteal($v, $candidate)) {
                return;
            }
        }
    }

    private static function hasSupplyScourCandidate(Player $victim, string $candidate): bool
    {
        return match ($candidate) {
            "pearl", "ender_pearl", "enderpearl" => $victim->getInventory()->contains(VanillaItems::ENDER_PEARL()->setCount(1)),
            "egapple", "enchanted_golden_apple", "enchantedgapple" => $victim->getInventory()->contains(VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(1)),
            "gapple", "golden_apple", "goldenapple" => $victim->getInventory()->contains(VanillaItems::GOLDEN_APPLE()->setCount(1)),
            "potion", "normal_potion" => self::inventoryHasSupplyScourPotionType($victim, ItemTypeIds::POTION),
            "splash_potion", "splash" => self::inventoryHasSupplyScourPotionType($victim, ItemTypeIds::SPLASH_POTION),
            "lingering_potion", "lingering" => self::inventoryHasSupplyScourPotionType($victim, ItemTypeIds::LINGERING_POTION),
            "any_potion", "pot", "heal_potion" => self::inventoryHasSupplyScourPotionType($victim, ItemTypeIds::SPLASH_POTION)
                || self::inventoryHasSupplyScourPotionType($victim, ItemTypeIds::LINGERING_POTION)
                || self::inventoryHasSupplyScourPotionType($victim, ItemTypeIds::POTION),
            default => false,
        };
    }

    private static function trySupplyScourSteal(Player $victim, string $candidate): bool
    {
        return match ($candidate) {
            "pearl", "ender_pearl", "enderpearl" => self::removeSupplyScourExact($victim, VanillaItems::ENDER_PEARL()->setCount(1), "&r&cMarauder &7stole an ender pearl."),
            "egapple", "enchanted_golden_apple", "enchantedgapple" => self::removeSupplyScourExact($victim, VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(1), "&r&cMarauder &7stole an enchanted golden apple."),
            "gapple", "golden_apple", "goldenapple" => self::removeSupplyScourExact($victim, VanillaItems::GOLDEN_APPLE()->setCount(1), "&r&cMarauder &7stole a golden apple."),
            "potion", "normal_potion" => self::removeSupplyScourPotionType($victim, ItemTypeIds::POTION, "&r&cMarauder &7stole a potion."),
            "splash_potion", "splash" => self::removeSupplyScourPotionType($victim, ItemTypeIds::SPLASH_POTION, "&r&cMarauder &7stole a splash potion."),
            "lingering_potion", "lingering" => self::removeSupplyScourPotionType($victim, ItemTypeIds::LINGERING_POTION, "&r&cMarauder &7stole a lingering potion."),
            "any_potion", "pot", "heal_potion" => self::removeSupplyScourAnyPotion($victim),
            default => false,
        };
    }

    private static function removeSupplyScourExact(Player $victim, \pocketmine\item\Item $item, string $message): bool
    {
        if (!$victim->getInventory()->contains($item)) {
            return false;
        }

        $victim->getInventory()->removeItem($item);
        $victim->sendMessage(C::colorize($message));
        return true;
    }

    private static function removeSupplyScourPotionType(Player $victim, int $typeId, string $message): bool
    {
        foreach ($victim->getInventory()->getContents() as $item) {
            if ($item->getTypeId() !== $typeId) {
                continue;
            }

            $removed = clone $item;
            $removed->setCount(1);
            $victim->getInventory()->removeItem($removed);
            $victim->sendMessage(C::colorize($message));
            return true;
        }

        return false;
    }

    private static function removeSupplyScourAnyPotion(Player $victim): bool
    {
        foreach ([
            ItemTypeIds::SPLASH_POTION => "&r&cMarauder &7stole a splash potion.",
            ItemTypeIds::LINGERING_POTION => "&r&cMarauder &7stole a lingering potion.",
            ItemTypeIds::POTION => "&r&cMarauder &7stole a potion.",
        ] as $typeId => $message) {
            if (self::removeSupplyScourPotionType($victim, $typeId, $message)) {
                return true;
            }
        }

        return false;
    }

    private static function inventoryHasSupplyScourPotionType(Player $victim, int $typeId): bool
    {
        foreach ($victim->getInventory()->getContents() as $item) {
            if ($item->getTypeId() === $typeId) {
                return true;
            }
        }

        return false;
    }

    private static function trifoldRend(EntityDamageByEntityEvent $e, int $l): void
    {
        $d = $e->getDamager();
        $v = $e->getEntity();
        if (!$d instanceof Player || !$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "thirdeye") < 1) {
            return;
        }
        if (!AdvancedEnchantState::bumpTrifoldAndIsThird($d, $v)) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("thirdeye", "chance-by-level", $l, 6 + 2 * $l);
        if (ProcLuckManager::roll($d, "thirdeye", $chance, 1, 5)) {
            $damageMultiplier = max(0.0, EnchantTuningManager::getLevelFloat("thirdeye", "damage-multiplier-by-level", $l, 2.0));
            $e->setBaseDamage($e->getBaseDamage() * $damageMultiplier);
        }
    }

    private static function ironMuzzle(EntityDamageByEntityEvent $e, int $l): void
    {
        $d = $e->getDamager();
        $v = $e->getEntity();
        if (!$d instanceof Player || !$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "lockjaw") < 1) {
            return;
        }
        $chance = EnchantTuningManager::getLevelInt("lockjaw", "chance-by-level", $l, 2);
        if (!ProcLuckManager::roll($d, "lockjaw", $chance, 1, 1)) {
            return;
        }
        $muzzleS = max(1, EnchantTuningManager::getLevelInt("lockjaw", "muzzle-seconds-by-level", $l, EnchantTuningManager::getInt("lockjaw", "muzzle-seconds", 3)));
        AdvancedEnchantState::muzzle($v, $muzzleS);
        $v->sendMessage(C::colorize("&r&7You were &fIron Muzzled &7for {$muzzleS}s."));
    }
}
