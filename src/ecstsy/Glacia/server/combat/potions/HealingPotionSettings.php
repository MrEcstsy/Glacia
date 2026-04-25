<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\combat\potions;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\utils\Config;

final class HealingPotionSettings {

    private Config $config;

    public function __construct()
    {
        $this->config = GeneralUtils::getConfiguration(Loader::getInstance(), "combat.yml");
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config->getNested("healing-potions.enabled", true);
    }

    public function isSelfSplashEnabled(): bool
    {
        return (bool) $this->config->getNested("healing-potions.self-splash.enabled", true);
    }

    public function getSelfSplashDistance(): float
    {
        return (float) $this->config->getNested("healing-potions.self-splash.max-impact-distance", 2.35);
    }

    public function isPitchAssistEnabled(): bool
    {
        return (bool) $this->config->getNested("healing-potions.self-splash.pitch-assist.enabled", true);
    }

    public function getPitchAssistMinLookUpPitch(): float
    {
        return (float) $this->config->getNested("healing-potions.self-splash.pitch-assist.min-look-up-pitch", -55.0);
    }

    public function getNormalSelfBonus(): float
    {
        return (float) $this->config->getNested("healing-potions.self-splash.normal-bonus", 0.5);
    }

    public function getStrongSelfBonus(): float
    {
        return (float) $this->config->getNested("healing-potions.self-splash.strong-bonus", 1.0);
    }

    public function getPitchAssistBonusHeal(): float
    {
        return (float) $this->config->getNested("healing-potions.self-splash.pitch-assist.bonus-heal", 0.5);
    }

    public function isDirectHitEnabled(): bool
    {
        return (bool) $this->config->getNested("healing-potions.self-splash.direct-hit.enabled", true);
    }

    public function getDirectHitNormalBonus(): float
    {
        return (float) $this->config->getNested("healing-potions.self-splash.direct-hit.normal-bonus", 1.0);
    }

    public function getDirectHitStrongBonus(): float
    {
        return (float) $this->config->getNested("healing-potions.self-splash.direct-hit.strong-bonus", 2.0);
    }
}