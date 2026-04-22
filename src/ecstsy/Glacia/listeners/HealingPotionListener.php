<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\combat\potions\HealingPotionSettings;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\potion\VanillaPotions;

final class HealingPotionListener implements Listener {

    public function __construct(
        private HealingPotionSettings $settings
    ){}

    public function onPotionSplash(PotionSplashEvent $event): void
    {
        if(!$this->settings->isEnabled()){
            return;
        }

        $potion = $event->getPotion();
        if(!$potion instanceof SplashPotion){
            return;
        }

        $strong = null;
        if(!$this->isHealingPotion($potion, $strong)){
            return;
        }

        $baseHeal = $strong ? $this->settings->getStrongHeal() : $this->settings->getNormalHeal();

        $affected = $event->getAffectedEntities();

        foreach($affected as $entity){
            if(!$entity instanceof Living || !$entity->isAlive()){
                continue;
            }

            $intensity = max(0.0, min($this->settings->getMaxIntensity(), $event->getIntensity($entity)));

            // Prevent vanilla instant-heal from applying
            $event->setIntensity($entity, 0.0);

            if($intensity <= 0.0){
                continue;
            }

            $this->healEntity($entity, $baseHeal * $intensity);
        }

        $this->handleSelfSplashAssist($event, $potion, $baseHeal, $affected);
    }

    /**
     * Gives the thrower a more reliable self-pot if the impact is close enough.
     *
     * @param list<Living> $affected
     */
    private function handleSelfSplashAssist(
        PotionSplashEvent $event,
        SplashPotion $potion,
        float $baseHeal,
        array $affected
    ): void {
        if(!$this->settings->isSelfSplashEnabled()){
            return;
        }

        $owner = $potion->getOwningEntity();
        if(!$owner instanceof Player || !$owner->isAlive()){
            return;
        }

        $impactPosition = $potion->getPosition();
        if(!$impactPosition->isValid()){
            return;
        }

        $distance = $owner->getPosition()->distance($impactPosition);
        if($distance > $this->settings->getSelfSplashDistance()){
            return;
        }

        $currentIntensity = 0.0;
        $alreadyAffected = false;

        foreach($affected as $entity){
            if($entity === $owner){
                $alreadyAffected = true;
                $currentIntensity = $event->getIntensity($owner);
                break;
            }
        }

        $targetIntensity = $this->settings->getMinSelfIntensity();

        if(
            $this->settings->isPitchAssistEnabled() &&
            $owner->getLocation()->getPitch() <= $this->settings->getPitchAssistMinLookUpPitch()
        ){
            $targetIntensity += $this->settings->getPitchAssistBonusIntensity();
        }

        $targetIntensity = max(0.0, min($this->settings->getMaxIntensity(), $targetIntensity));

        if($alreadyAffected){
            // Vanilla heal was disabled above, so only give the missing difference.
            $missingIntensity = max(0.0, $targetIntensity - $currentIntensity);
            if($missingIntensity > 0.0){
                $this->healEntity($owner, $baseHeal * $missingIntensity);
            }
            return;
        }

        // Not affected at all by vanilla splash, but impact was close enough to count as a self-pot.
        $this->healEntity($owner, $baseHeal * $targetIntensity);
    }

    private function healEntity(Living $entity, float $amount): void
    {
        if($amount <= 0.0 || !$entity->isAlive()){
            return;
        }

        $maxHealth = $entity->getMaxHealth();
        $newHealth = min($maxHealth, $entity->getHealth() + $amount);

        if($newHealth > $entity->getHealth()){
            $entity->setHealth($newHealth);
        }
    }

    private function isHealingPotion(SplashPotion $potion, ?bool &$strong = false): bool
    {
        $strong = false;

        $type = $potion->getPotionType();

        foreach($type->getEffects() as $effect){
            $effectType = $effect->getType();

            if($effectType !== VanillaEffects::INSTANT_HEALTH()){
                continue;
            }

            $amplifier = method_exists($effect, "getAmplifier") ? $effect->getAmplifier() : 0;
            $strong = $amplifier >= 1;
            return true;
        }

        return false;
    }
}