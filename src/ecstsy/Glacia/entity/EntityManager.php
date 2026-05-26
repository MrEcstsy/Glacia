<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\entity\mobs\Blaze;
use ecstsy\Glacia\entity\mobs\Bee;
use ecstsy\Glacia\entity\mobs\Chicken;
use ecstsy\Glacia\entity\mobs\Cow;
use ecstsy\Glacia\entity\mobs\Creeper;
use ecstsy\Glacia\entity\mobs\Enderman;
use ecstsy\Glacia\entity\mobs\Ghast;
use ecstsy\Glacia\entity\mobs\IronGolem;
use ecstsy\Glacia\entity\mobs\MagmaCube;
use ecstsy\Glacia\entity\mobs\Mooshroom;
use ecstsy\Glacia\entity\mobs\Pig;
use ecstsy\Glacia\entity\mobs\Sheep;
use ecstsy\Glacia\entity\mobs\Skeleton;
use ecstsy\Glacia\entity\mobs\Spider;
use ecstsy\Glacia\entity\mobs\Slime;
use ecstsy\Glacia\entity\mobs\SnowGolem;
use ecstsy\Glacia\entity\mobs\Squid;
use ecstsy\Glacia\entity\mobs\Warden;
use ecstsy\Glacia\entity\mobs\Zombie;
use ecstsy\Glacia\entity\mobs\ZombiePigman;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use RuntimeException as GlobalRuntimeException;

final class EntityManager
{

    use SingletonTrait;

    private array $entities = [
        Blaze::class,
        Bee::class,
        Chicken::class,
        Cow::class,
        Creeper::class,
        Enderman::class,
        Ghast::class,
        IronGolem::class,
        MagmaCube::class,
        Mooshroom::class,
        Pig::class,
        Skeleton::class,
        Slime::class,
        Spider::class,
        Squid::class,
        Warden::class,
        Zombie::class,
        ZombiePigman::class,
        Sheep::class,
        SnowGolem::class,
    ];

    private array $entityIds = [];

    public function __construct() {
        self::$instance = $this;

        foreach($this->entities as $entity) {
            EntityFactory::getInstance()->register($entity, function(World $world, CompoundTag $nbt) use($entity): SpawnerEntity {
                return new $entity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
            }, [$entity::getNetworkTypeId()]);

            $this->entityIds[$entity::getNetworkTypeId()] = $entity;
        }
    }

    public function getEntityFor(string $entityTypeId, Location $location, $nbt) : SpawnerEntity {
        switch($entityTypeId) {
            case Blaze::getNetworkTypeId():
                return new Blaze($location, $nbt);
            case Bee::getNetworkTypeId():
                return new Bee($location, $nbt);
            case Chicken::getNetworkTypeId():
                return new Chicken($location, $nbt);
            case Cow::getNetworkTypeId():
                return new Cow($location, $nbt);
            case Creeper::getNetworkTypeId():
                return new Creeper($location, $nbt);
            case Enderman::getNetworkTypeId():
                return new Enderman($location, $nbt);
            case Ghast::getNetworkTypeId():
                return new Ghast($location, $nbt);
            case IronGolem::getNetworkTypeId():
                return new IronGolem($location, $nbt);
            case MagmaCube::getNetworkTypeId():
                return new MagmaCube($location, $nbt);
            case Mooshroom::getNetworkTypeId():
                return new Mooshroom($location, $nbt);
            case Pig::getNetworkTypeId():
                return new Pig($location, $nbt);
            case Skeleton::getNetworkTypeId():
                return new Skeleton($location, $nbt);
            case Slime::getNetworkTypeId():
                return new Slime($location, $nbt);
            case Spider::getNetworkTypeId():
                return new Spider($location, $nbt);
            case Squid::getNetworkTypeId():
                return new Squid($location, $nbt);
            case Warden::getNetworkTypeId():
                return new Warden($location, $nbt);
            case Zombie::getNetworkTypeId():
                return new Zombie($location, $nbt);
            case ZombiePigman::getNetworkTypeId():
                return new ZombiePigman($location, $nbt);
            case Sheep::getNetworkTypeId():
                return new Sheep($location, $nbt);
            case SnowGolem::getNetworkTypeId():
                return new SnowGolem($location, $nbt);
            default:
                throw new GlobalRuntimeException("Invalid entity type id: $entityTypeId");
        }
    }
}
