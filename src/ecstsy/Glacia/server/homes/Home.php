<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\homes;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;
use Ramsey\Uuid\UuidInterface;

final class Home
{


    public function __construct(
        public UuidInterface $uuid,
        public string        $home_name,
        public string        $world_name,
        public int           $x,
        public int           $y,
        public int           $z,
        public int           $limit
    )
    {
    }

    /**
     * Get UUID of the owner
     *
     * @return UuidInterface
     */
    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    /**
     * This function gets the PocketMine player
     *
     * @return Player|null
     */
    public function getOwnerPlayer(): ?Player
    {
        return Server::getInstance()->getPlayerByUUID($this->uuid);
    }

    /**
     * Get home's name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->home_name;
    }

    /**
     * Get the world of the home
     *
     * @return World|null
     */
    public function getWorld(): ?World
    {
        $worldManager = Server::getInstance()->getWorldManager();
        $world = $worldManager->getWorldByName($this->world_name);
        if ($world === null && $this->world_name !== "" && $worldManager->loadWorld($this->world_name)) {
            $world = $worldManager->getWorldByName($this->world_name);
        }

        return $world;
    }

    /**
     * Get the position of the home
     *
     * @return Position|null
     */
    public function getPosition(): ?Position
    {
        return ($world = $this->getWorld()) === null ? null : (new Position($this->x, $this->y, $this->z, $world));
    }

    /**
     * Utility function to teleport player directly from the home call
     *
     * @param Player $player
     * @return bool
     */
    public function teleport(Player $player): bool
    {
        if (($pos = $this->getPosition()) === null) {
            return false;
        }
        $player->teleport($pos);
        return true;
    }
}
