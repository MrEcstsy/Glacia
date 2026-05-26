<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\world\theme;

use pocketmine\block\VanillaBlocks;
use pocketmine\color\Color;
use pocketmine\item\VanillaItems;
use pocketmine\world\particle\AngryVillagerParticle;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\CriticalParticle;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\particle\EnchantmentTableParticle;
use pocketmine\world\particle\EnchantParticle;
use pocketmine\world\particle\EntityFlameParticle;
use pocketmine\world\particle\ExplodeParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\particle\InkParticle;
use pocketmine\world\particle\InstantEnchantParticle;
use pocketmine\world\particle\ItemBreakParticle;
use pocketmine\world\particle\LavaDripParticle;
use pocketmine\world\particle\LavaParticle;
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\particle\RainSplashParticle;
use pocketmine\world\particle\RedstoneParticle;
use pocketmine\world\particle\SmokeParticle;
use pocketmine\world\particle\SonicExplosionParticle;
use pocketmine\world\particle\SporeParticle;
use pocketmine\world\particle\WaterDripParticle;
use pocketmine\world\particle\WaterParticle;

/**
 * Maps theme/config particle names to vanilla PM particles.
 * Client resource packs (e.g. Upsilon "UP*" packs) retexture these ids — use crit/enchant/portal/flame etc.
 */
final class AmbientParticleFactory
{
    private function __construct()
    {
    }

    public static function create(string $name): ?Particle
    {
        $key = strtolower(str_replace([" ", "-"], "_", trim($name)));

        return match ($key) {
            // Ice / Frostbite — block crack particles
            "packed_ice", "blue_ice", "ice" => new BlockBreakParticle(VanillaBlocks::PACKED_ICE()),
            "snow", "snow_layer" => new BlockBreakParticle(VanillaBlocks::SNOW()),
            // End / astral — matches many "void" RP overlays on portal + end rod sparkles
            "portal", "end_portal", "void", "astral" => new PortalParticle(),
            // Keep end-rod aliases non-block based to avoid break-sound ambience loops.
            "end_rod", "endrod", "rod" => new EnchantmentTableParticle(),
            // Combat highlights — UPJavaCrit-style packs replace crit textures
            "crit", "critical", "javacrit", "java_crit", "upjavacrit" => new CriticalParticle(),
            // Enchant / spell — enchant icon packs often hook these
            "enchant", "enchanting", "enchantment", "glyph" => new EnchantParticle(new Color(140, 90, 255, 220)),
            "enchant_table", "enchantment_table", "table" => new EnchantmentTableParticle(),
            "spell", "mob_spell" => new EnchantParticle(new Color(0, 0, 0, 255)),
            "instant_spell", "instant_enchant" => new InstantEnchantParticle(new Color(0, 0, 0, 255)),
            // Fire / lava
            "flame", "fire" => new FlameParticle(),
            "entity_flame", "mobflame" => new EntityFlameParticle(),
            "lava" => new LavaParticle(),
            "lava_drip", "driplava" => new LavaDripParticle(),
            // Dust / utility
            "reddust", "redstone", "dust" => new RedstoneParticle(1),
            "white_dust", "snow_dust" => new DustParticle(new Color(220, 245, 255, 200)),
            "smoke" => new SmokeParticle(0),
            // Water / cold ambient
            "water", "splash", "wake" => new WaterParticle(),
            "bubble" => new BubbleParticle(),
            "dripwater", "water_drip" => new WaterDripParticle(),
            "droplet", "rain" => new RainSplashParticle(),
            // Misc vanilla (often retextured in particle packs)
            "heart" => new HeartParticle(0),
            "ink" => new InkParticle(0),
            "happy_villager" => new HappyVillagerParticle(),
            "spore", "townaura" => new SporeParticle(),
            "angry_villager" => new AngryVillagerParticle(),
            "explode", "explosion" => new ExplodeParticle(),
            "hugeexplosion", "huge_explosion" => new HugeExplodeParticle(),
            "sonic_explosion", "sonic" => new SonicExplosionParticle(),
            "snowball_poof", "snowballpoof" => new ItemBreakParticle(VanillaItems::SNOWBALL()),
            default => null,
        };
    }

    /**
     * Same as create(), but falls back to portal particles so themes never silently no-op.
     */
    public static function createOrPortal(string $name): Particle
    {
        return self::create($name) ?? new PortalParticle();
    }
}
