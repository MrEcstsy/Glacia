<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\fishing;

use pocketmine\block\Air;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\item\Durable;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\WaterParticle;
use function abs;
use function mt_rand;
use function sqrt;

/**
 * Bedrock fishing hook projectile + bite timing, adapted from VanillaFishing (Apache-2.0).
 *
 * @see https://github.com/kostamax27/VanillaFishing
 */
final class GlaciaFishingHook extends Projectile implements NeverSavedWithChunkEntity{

    public static function getNetworkTypeId() : string{
        return EntityIds::FISHING_HOOK;
    }

    private int $waitingTimer = 120;
    private bool $attracted = false;
    private bool $caught = false;
    private int $caughtTimer = 0;
    private ?Vector3 $fish = null;

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(0.25, 0.25, 0.125, 0.0);
    }

    protected function getInitialDragMultiplier() : float{
        return 0.02;
    }

    protected function getInitialGravity() : float{
        return 0.05;
    }

    protected function initEntity(CompoundTag $nbt) : void{
        parent::initEntity($nbt);
        $this->waitingTimer = $this->scaleWaitTicks(mt_rand(20, 40));
    }

    private function scaleWaitTicks(int $baseTicks) : int{
        $owner = $this->getOwningEntity();
        if(!$owner instanceof Player){
            return max(1, $baseTicks);
        }

        return FishingManager::scaleTicksForRodRune($owner, $baseTicks);
    }

    public function isCaught() : bool{
        return $this->caught;
    }

    protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
        parent::onHitEntity($entityHit, $hitResult);
        $this->setTargetEntity($entityHit);
    }

    protected function despawnsOnEntityHit() : bool{
        return false;
    }

    public function canCollideWith(Entity $entity) : bool{
        return $entity->getId() !== $this->ownerId && $this->getTargetEntity() === null;
    }

    public function onUpdate(int $currentTick) : bool{
        $target = $this->getTargetEntity();
        if($target !== null){
            if(!$target->isAlive()){
                $this->setTargetEntity(null);
            }else{
                $this->setPositionAndRotation($target->getPosition()->add(0, $target->getEyeHeight(), 0), 0, 0);
                $this->setForceMovementUpdate();
            }
        }
        $player = $this->getOwningEntity();
        if($player === null || ($player instanceof Player && $this->getPosition()->distance($player->getPosition()->asVector3()) >= 32)){
            $this->flagForDespawn();
        }
        $hasUpdate = parent::onUpdate($currentTick);
        if(!$hasUpdate){
            return false;
        }
        if($this->isUnderwater()){
            $this->motion->x = 0;
            $this->motion->y = 0.16;
            $this->motion->z = 0;
        }elseif($this->isCollided && $this->keepMovement){
            $this->motion->x = 0;
            $this->motion->z = 0;
            $this->keepMovement = false;
        }
        if($this->isUnderwater()){
            if(!$this->attracted){
                if($this->waitingTimer > 0){
                    --$this->waitingTimer;
                }
                if($this->waitingTimer === 0){
                    $this->spawnFish();
                    $this->caught = false;
                    $this->attracted = true;
                }
            }elseif(!$this->caught){
                if($this->attractFish()){
                    $random = new Random();
                    $this->caughtTimer = $random->nextBoundedInt(20) + 20;
                    $this->fishBites();
                    $this->caught = true;
                }
            }else{
                if($this->caughtTimer > 0){
                    --$this->caughtTimer;
                }
                if($this->caughtTimer === 0){
                    $this->attracted = false;
                    $this->caught = false;
                    $this->waitingTimer = $this->scaleWaitTicks(mt_rand(40, 60));
                }
            }
        }
        return true;
    }

    public function getWaterHeight() : int{
        for($y = $this->getPosition()->getFloorY(); $y < 256; $y++){
            $block = $this->getWorld()->getBlockAt($this->getPosition()->getFloorX(), $y, $this->getPosition()->getFloorZ());
            if($block instanceof Air){
                return $y;
            }
        }
        return $this->getPosition()->getFloorY();
    }

    public function spawnFish() : void{
        $random = new Random();
        $position = $this->getPosition();
        $this->fish = new Vector3(
            $position->x + ($random->nextFloat() * 1.2 + mt_rand(1, 4)) * ($random->nextFloat() ? -1 : 1),
            $this->getWaterHeight(),
            $position->z + ($random->nextFloat() * 1.2 + mt_rand(1, 4)) * ($random->nextFloat() ? -1 : 1)
        );
    }

    public function reelLine() : void{
        $player = $this->getOwningEntity();
        if($player instanceof Player){
            FishingManager::clearHookFor($player, $this);
            if($this->caught){
                FishingManager::grantCatchFromHook($player);
            }else{
                FishingManager::notifyHookReelEmpty($player);
            }

            $hand = $player->getInventory()->getItemInHand();
            if($hand instanceof Durable && $hand->getDamage() < $hand->getMaxDurability() - 1){
                $damage = 0;
                if($this->caught){
                    $damage += 1;
                }
                if($this->getTargetEntity() !== null){
                    $damage += mt_rand(1, 2);
                }
                if($damage > 0){
                    $hand->setDamage(min($hand->getMaxDurability() - 1, $hand->getDamage() + $damage));
                    $player->getInventory()->setItemInHand($hand);
                }
            }

            if($this->getTargetEntity() !== null){
                $target = $this->getTargetEntity();
                if ($target instanceof Living) {
                    $dx = $player->getPosition()->x - $target->getPosition()->x;
                    $dy = ($player->getPosition()->y + 0.8) - $target->getPosition()->y;
                    $dz = $player->getPosition()->z - $target->getPosition()->z;
                    $len = sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
                    if ($len > 0.0001) {
                        $pull = 1.15;
                        $target->setMotion(new Vector3(($dx / $len) * $pull, max(0.22, ($dy / $len) * $pull), ($dz / $len) * $pull));
                    }
                } else {
                    $target->setMotion($player->getDirectionVector()->multiply(-1));
                }
            }
        }
        $this->flagForDespawn();
    }

    public function attractFish() : bool{
        if($this->fish === null){
            return false;
        }
        $multiply = 0.1;
        $position = $this->getPosition();
        $this->fish = $this->fish->withComponents(
            $this->fish->x + ($position->x - $this->fish->x) * $multiply,
            $this->fish->y,
            $this->fish->z + ($position->z - $this->fish->z) * $multiply
        );
        $this->getWorld()->addParticle($this->fish, new WaterParticle());
        $this->getWorld()->addParticle($this->fish->add(0, 0.08, 0), new BubbleParticle());
        $dist = abs(sqrt($position->x * $position->x + $position->z * $position->z) - sqrt($this->fish->x * $this->fish->x + $this->fish->z * $this->fish->z));
        return $dist < 0.15;
    }

    public function fishBites() : void{
        NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [
            ActorEventPacket::create($this->getId(), ActorEvent::FISH_HOOK_HOOK, 0, null),
            ActorEventPacket::create($this->getId(), ActorEvent::FISH_HOOK_BUBBLE, 0, null),
            ActorEventPacket::create($this->getId(), ActorEvent::FISH_HOOK_TEASE, 0, null),
        ]);

        $random = new Random();
        $position = $this->getPosition();
        for($i = 0; $i < 10; $i++){
            $bubblePos = $position->withComponents(
                $position->x + $random->nextFloat() * 0.7 - 0.35,
                $this->getWaterHeight(),
                $position->z + $random->nextFloat() * 0.7 - 0.35
            );
            $this->getWorld()->addParticle($bubblePos, new BubbleParticle());
            if($i % 2 === 0){
                $this->getWorld()->addParticle($bubblePos->add(0, 0.08, 0), new WaterParticle());
            }
        }
        --$this->motion->y;
    }

    protected function onDispose() : void{
        $player = $this->getOwningEntity();
        if($player instanceof Player){
            FishingManager::clearHookFor($player, $this);
        }
        parent::onDispose();
    }

    public static function cast(Player $player) : bool{
        $existing = FishingManager::getPlayerHook($player);
        if($existing !== null){
            if($existing->isFlaggedForDespawn()){
                FishingManager::clearHookFor($player, $existing);
            }else{
                return false;
            }
        }

        $eye = $player->getEyePos();
        $loc = Location::fromObject($eye, $player->getWorld(), $player->getLocation()->getYaw(), $player->getLocation()->getPitch());
        $nbt = CompoundTag::create();
        $hook = new GlaciaFishingHook($loc, $player, $nbt);
        $hook->setMotion($player->getDirectionVector()->multiply(0.9));

        $ev = new \pocketmine\event\entity\ProjectileLaunchEvent($hook);
        $ev->call();
        if($ev->isCancelled()){
            $hook->flagForDespawn();
            return false;
        }

        $hook->spawnToAll();
        $player->getWorld()->addSound($loc, new \pocketmine\world\sound\ThrowSound(), [$player]);
        FishingManager::registerHook($player, $hook);
        return true;
    }
}
