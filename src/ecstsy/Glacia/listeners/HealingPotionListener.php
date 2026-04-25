<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\combat\potions\HealingPotionSettings;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;

final class HealingPotionListener implements Listener {

    public function __construct(
        private HealingPotionSettings $settings
    ){}

    public function onProjectileHit(ProjectileHitEvent $event): void
    {
        if(!$this->settings->isEnabled()){
            return;
        }

        $projectile = $event->getEntity();
        if(!$projectile instanceof SplashPotion){
            return;
        }

        $owner = $projectile->getOwningEntity();
        if(!$owner instanceof Player || !$owner->isAlive()){
            return;
        }

        $strong = false;
        if(!$this->isHealingPotion($projectile, $strong)){
            return;
        }

        if(!$this->settings->isSelfSplashEnabled()){
            return;
        }

        $impactPosition = $projectile->getPosition();
        if(!$impactPosition->isValid()){
            return;
        }

        $distance = $owner->getPosition()->distance($impactPosition);
        if($distance > $this->settings->getSelfSplashDistance()){
            return;
        }

        $directHitOnOwner =
            $event instanceof ProjectileHitEntityEvent &&
            $event->getEntityHit() === $owner;

        if($directHitOnOwner && $this->settings->isDirectHitEnabled()){
            $bonus = $strong
                ? $this->settings->getDirectHitStrongBonus()
                : $this->settings->getDirectHitNormalBonus();
        }else{
            $bonus = $strong
                ? $this->settings->getStrongSelfBonus()
                : $this->settings->getNormalSelfBonus();
        }

        if(
            $this->settings->isPitchAssistEnabled() &&
            $owner->getLocation()->getPitch() <= $this->settings->getPitchAssistMinLookUpPitch()
        ){
            $bonus += $this->settings->getPitchAssistBonusHeal();
        }

        if($bonus <= 0){
            return;
        }

        $owner->setHealth(
            min($owner->getMaxHealth(), $owner->getHealth() + $bonus)
        );
    }

    private function isHealingPotion(SplashPotion $potion, bool &$strong = false): bool
    {
        $strong = false;

        foreach($potion->getPotionType()->getEffects() as $effect){
            if($effect->getType() !== VanillaEffects::INSTANT_HEALTH()){
                continue;
            }

            $strong = $effect->getAmplifier() >= 1;
            return true;
        }

        return false;
    }
}