<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

/**
 * Weapon-side enchants that are considered "negative" for Maliceward immunity.
 * (Debuff, control, damage spike from opponent POV when they hit you.)
 */
final class MaliceWeaponRegistry
{
    private const NEGATIVE = [
        "disarmor", "slowness", "blind", "iceaspect", "confusion", "curse", "insomnia", "fear", "holy", "pummel",
        "vampire", "hexlock", "silence", "brotherhood", "execute", "deathbringer", "diminish", "thunderfamine",
        "demonicfinisher", "siegebreaker", "blackhand", "jeopardy", "vampiricdevour", "plague", "wrathbreak",
        "stormlash", "divinechains", "dreadanchor", "anatomy",
    ];

    public static function isNegativeWeaponEnchant(string $name): bool
    {
        return in_array(strtolower($name), self::NEGATIVE, true);
    }
}
