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

    public function getNormalHeal(): float
    {
        return (float) $this->config->getNested("healing-potions.normal.heal", 7.0);
    }

    public function getStrongHeal(): float
    {
        return (float) $this->config->getNested("healing-potions.strong.heal", 9.0);
    }

    public function isSelfSplashEnabled(): bool
    {
        return (bool) $this->config->getNested("healing-potions.self-splash.enabled", true);
    }

    public function getSelfSplashDistance(): float
    {
        return (float) $this->config->getNested("healing-potions.self-splash.max-impact-distance", 2.35);
    }

    public function getMinSelfIntensity(): float
    {
        return (float) $this->config->getNested("healing-potions.self-splash.min-intensity", 0.90);
    }

    public function isPitchAssistEnabled(): bool
    {
        return (bool) $this->config->getNested("healing-potions.self-splash.pitch-assist.enabled", true);
    }

    public function getPitchAssistMinLookUpPitch(): float
    {
        return (float) $this->config->getNested("healing-potions.self-splash.pitch-assist.min-look-up-pitch", -55.0);
    }

    public function getPitchAssistBonusIntensity(): float
    {
        return (float) $this->config->getNested("healing-potions.self-splash.pitch-assist.bonus-intensity", 0.10);
    }

    public function getMaxIntensity(): float
    {
        return (float) $this->config->getNested("healing-potions.max-intensity", 1.0);
    }
}