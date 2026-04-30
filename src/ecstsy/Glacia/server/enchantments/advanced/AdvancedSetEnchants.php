<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\advanced;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\discord\DiscordLinkManager;
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
use ecstsy\Glacia\server\items\armor\ArmorSetManager;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\utils\GlaciaUtils;
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
            "furylash" => self::furylash($event, $level),
            "glimmerfade" => self::glimmerfade($event, $level),
            "forewarned" => self::forewarned($event, $level),
            "maliceward" => self::maliceward($event, $level),
            "vilewound" => self::vilewound($event, $level),
            "coupdegrace" => self::coupdegrace($event, $level),
            "harmonicsurge" => self::harmonicSurge($event, $level),
            "phalanx" => self::phalanx($event, $level),
            "stormrite" => self::stormrite($event, $level),
            "netherwarden" => self::netherWarden($event, $level),
            "auroraward" => self::auroraWard($event, $level),
            "primalpact" => self::primalPact($event, $level),
            "discordverdict" => self::discordVerdict($event, $level),
            "alchemistedge" => self::alchemistEdge($event, $level),
            "supplyscour" => self::supplyScour($event, $level),
            "trifoldrend" => self::trifoldRend($event, $level),
            "ironmuzzle" => self::ironMuzzle($event, $level),
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
            if (CustomEnchantmentManager::getLevel($helD, "triadward") >= 4) {
                $event->setBaseDamage($event->getBaseDamage() * 0.9);
            }
            $helV = $victim->getArmorInventory()->getHelmet();
            if (!($helV->isNull())) {
                $ht = CustomEnchantmentManager::getLevel($helV, "hearttide");
                if ($ht > 0) {
                    $max = $victim->getMaxHealth();
                    $f = $max > 0 ? $victim->getHealth() / $max : 0.5;
                    $w = 0.08 * $ht;
                    $mult = 1.0 + $w * (0.5 - $f) * 2.0;
                    $event->setBaseDamage($event->getBaseDamage() * max(0.35, $mult));
                }
                $tw = CustomEnchantmentManager::getLevel($helV, "triadward");
                if ($tw > 0) {
                    $set = ArmorSetManager::getInstance()->getFullSet($damager);
                    if (in_array($set, ["phantom", "yijki", "yeti"], true)) {
                        $red = 0.05 * min(5, $tw);
                        $event->setBaseDamage($event->getBaseDamage() * (1.0 - $red));
                    }
                }
            }
            $b = $victim->getArmorInventory()->getBoots();
            if (!$b->isNull()) {
                $aa = CustomEnchantmentManager::getLevel($b, "asceticaegis");
                if ($aa > 0) {
                    $n = count(CustomEnchantmentManager::getEnchantments($damager->getInventory()->getItemInHand()));
                    $n = min(30, $n);
                    $m = 1.0 - min(0.45, 0.01 * $aa * $n);
                    if ($damager->getPosition()->distanceSquared($victim->getPosition()) <= 3.5 * 3.5) {
                        $event->setBaseDamage($event->getBaseDamage() * max(0.2, $m));
                    } else {
                        $vp = $victim->getPosition();
                        foreach ($victim->getWorld()->getPlayers() as $ally) {
                            if ($ally === $victim) {
                                continue;
                            }
                            if (!GlaciaUtils::isAlly($victim, $ally)) {
                                continue;
                            }
                            if ($ally->getPosition()->distanceSquared($vp) > 3.5 * 3.5) {
                                continue;
                            }
                            if ($damager->getPosition()->distanceSquared($vp) <= 3.5 * 3.5) {
                                $event->setBaseDamage($event->getBaseDamage() * max(0.2, $m));
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
        return CustomEnchantmentManager::getLevel($p->getInventory()->getItemInHand(), "steadfastfury") > 0;
    }

    // ---

    private static function furylash(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        $d = $e->getDamager();
        if (!$v instanceof Player || !$d instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getChestplate(), "furylash") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($v, "furylash", min(100, $l), 1, 6)) {
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
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getChestplate(), "glimmerfade") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($v, "glimmerfade", 2 + 2 * $l, 2, 6)) {
            return;
        }
        AdvancedEnchantState::setGlimmerSuppressed($d, 2, $l);
    }

    private static function forewarned(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        if (!$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getChestplate(), "forewarned") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($v, "forewarned", 4 + 3 * $l, 1, 5)) {
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
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getChestplate(), "maliceward") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($v, "maliceward", 2 + 2 * $l, 1, 6)) {
            return;
        }
        AdvancedEnchantState::setMalicewardImmune($v, 5, $l);
    }

    private static function vilewound(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        $d = $e->getDamager();
        if (!$v instanceof Player || !$d instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getChestplate(), "vilewound") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($v, "vilewound", 2 + 2 * $l, 1, 5)) {
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
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getLeggings(), "coupdegrace") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($v, "coupdegrace", 5, 1, 4)) {
            return;
        }
        if ($v->getHealth() <= 10.0) {
            AdvancedEnchantState::setCoupInvuln($v, 2);
        }
        if ($d->getHealth() <= 10.0) {
            AdvancedEnchantState::setCoupAttackerSilence($d, 7);
        }
    }

    private static function harmonicSurge(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        if (!$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getLeggings(), "harmonicsurge") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($v, "harmonicsurge", $l, 1, 3)) {
            return;
        }
        $cands = [];
        foreach ($v->getArmorInventory()->getContents() as $part) {
            if ($part->isNull()) {
                continue;
            }
            foreach (CustomEnchantmentManager::getEnchantments($part) as $k => $lv) {
                if (strtolower($k) === "harmonicsurge") {
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
            $v->setHealth(min($v->getMaxHealth(), $v->getHealth() + 8.0));
        }
    }

    private static function phalanx(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        if (!$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getLeggings(), "phalanx") < 1) {
            return;
        }
        if ($e->getDamager() instanceof Player) {
            EnchantCombatStateManager::registerIncomingAttacker($v, $e->getDamager(), 8);
        }
        $n = EnchantCombatStateManager::countRecentAttackers($v);
        if ($n < 1) {
            $n = 1;
        }
        $amp = (int) min(3, $n - 1);
        $v->getEffects()->add(new EffectInstance(VanillaEffects::REGENERATION(), 20 * 2, max(0, $amp), false, false));
    }

    private static function stormrite(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        $d = $e->getDamager();
        if (!$v instanceof Player || !$d instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getLeggings(), "stormrite") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($v, "stormrite", 1 + 2 * $l, 1, 3)) {
            return;
        }
        $blockS = max(1, EnchantTuningManager::getInt("stormrite", "gapple-block-seconds", 5));
        PetManager::blockGapples($d, $blockS);
        $arm = $d->getArmorInventory();
        foreach ($arm->getContents() as $s => $it) {
            if ($it instanceof Durable && !$it->isNull()) {
                $dd = (int) ceil($it->getMaxDurability() * 0.05);
                $it->setDamage(min($it->getMaxDurability(), $it->getDamage() + $dd));
                $arm->setItem($s, $it);
            }
        }
    }

    private static function netherWarden(EntityDamageByEntityEvent $e, int $l): void
    {
        $v = $e->getEntity();
        $d = $e->getDamager();
        if (!$v instanceof Player || !$d instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getBoots(), "netherwarden") < 1) {
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
        if (CustomEnchantmentManager::getLevel($v->getArmorInventory()->getBoots(), "auroraward") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($v, "auroraward", 3 + 2 * $l, 1, 4)) {
            return;
        }
        $m = $v->getWorld()->getTimeOfDay() > 6000 ? 1.0 : 2.0;
        $h = 0.5 * $l * (float) $m;
        $p = $v->getPosition();
        foreach ($v->getWorld()->getPlayers() as $pl) {
            if ($pl->getPosition()->distanceSquared($p) > 9 * 9) {
                continue;
            }
            if ($pl !== $v && !GlaciaUtils::isAlly($v, $pl)) {
                continue;
            }
            $pl->setHealth(min($pl->getMaxHealth(), $pl->getHealth() + $h));
        }
    }

    private static function primalPact(EntityDamageByEntityEvent $e, int $l): void
    {
        $d = $e->getDamager();
        if (!$d instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "primalpact") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($d, "primalpact", 3 + 2 * $l, 1, 5)) {
            return;
        }
        $pactS = max(1, EnchantTuningManager::getInt("primalpact", "pact-active-seconds", 10));
        AdvancedEnchantState::setPrimalPactWindow($d, $pactS, $l);
    }

    private static function discordVerdict(EntityDamageByEntityEvent $e, int $l): void
    {
        $d = $e->getDamager();
        $v = $e->getEntity();
        if (!$d instanceof Player || !$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "discordverdict") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($d, "discordverdict", 5, 1, 3)) {
            return;
        }
        $s = PlayerManager::getInstance()->getSession($v);
        $linked = $s !== null && DiscordLinkManager::isLinked($s);
        $f = 20;
        if (!$linked) {
            $f = 3 * 20;
            $v->attack(new EntityDamageEvent($v, EntityDamageEvent::CAUSE_MAGIC, $e->getFinalDamage()));
        }
        $v->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), $f, 4));
    }

    private static function alchemistEdge(EntityDamageByEntityEvent $e, int $l): void
    {
        $d = $e->getDamager();
        $v = $e->getEntity();
        if (!$d instanceof Player || !$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "alchemistedge") < 1) {
            return;
        }
        $pots = 0;
        foreach ($v->getInventory()->getContents() as $it) {
            if ($it->getTypeId() === ItemTypeIds::POTION) {
                $pots += $it->getCount();
            }
        }
        if ($pots === 0) {
            $e->setBaseDamage($e->getBaseDamage() * 0.75);
        } else {
            $b = intdiv($pots, 6);
            if ($b > 0) {
                $e->setBaseDamage($e->getBaseDamage() + 0.75 * $b);
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
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "supplyscour") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($d, "supplyscour", 1 + $l, 1, 6)) {
            return;
        }
        $rm = [];
        if ($v->getInventory()->contains(VanillaItems::ENDER_PEARL())) {
            $rm[] = "pearl";
        }
        if ($v->getInventory()->contains(VanillaItems::GOLDEN_APPLE())) {
            $rm[] = "gapple";
        }
        foreach ($v->getInventory()->getContents() as $i => $it) {
            if ($it->getTypeId() === ItemTypeIds::POTION) {
                $rm[] = "pot";
                break;
            }
        }
        if ($rm === []) {
            return;
        }
        $c = $rm[array_rand($rm)];
        if ($c === "pearl") {
            $v->getInventory()->removeItem(VanillaItems::ENDER_PEARL()->setCount(1));
        } elseif ($c === "gapple") {
            $v->getInventory()->removeItem(VanillaItems::GOLDEN_APPLE()->setCount(1));
        } else {
            foreach ($v->getInventory()->getContents() as $i => $it) {
                if ($it->getTypeId() === ItemTypeIds::POTION) {
                    $n = clone $it;
                    $n->setCount(1);
                    $v->getInventory()->removeItem($n);
                    break;
                }
            }
        }
    }

    private static function trifoldRend(EntityDamageByEntityEvent $e, int $l): void
    {
        $d = $e->getDamager();
        $v = $e->getEntity();
        if (!$d instanceof Player || !$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "trifoldrend") < 1) {
            return;
        }
        if (!AdvancedEnchantState::bumpTrifoldAndIsThird($d, $v)) {
            return;
        }
        if (ProcLuckManager::roll($d, "trifoldrend", 6 + 2 * $l, 1, 5)) {
            $e->setBaseDamage($e->getBaseDamage() * 2.0);
        }
    }

    private static function ironMuzzle(EntityDamageByEntityEvent $e, int $l): void
    {
        $d = $e->getDamager();
        $v = $e->getEntity();
        if (!$d instanceof Player || !$v instanceof Player) {
            return;
        }
        if (CustomEnchantmentManager::getLevel($d->getInventory()->getItemInHand(), "ironmuzzle") < 1) {
            return;
        }
        if (!ProcLuckManager::roll($d, "ironmuzzle", 2, 1, 1)) {
            return;
        }
        $muzzleS = max(1, EnchantTuningManager::getInt("ironmuzzle", "muzzle-seconds", 3));
        AdvancedEnchantState::muzzle($v, $muzzleS);
    }
}
