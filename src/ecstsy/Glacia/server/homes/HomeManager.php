<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\homes;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\server\factions\FactionNameBlocklist;
use ecstsy\Glacia\utils\PermissionTierUtils;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class HomeManager
{

    use SingletonTrait;

    /** @var Home[] */
    private array $homes = [];
    /** @var array<string, int> */
    private array $maxHomesByUuid = [];

    public function __construct(
        public Loader $plugin,
        public int $limit
    ){
        self::setInstance($this);

        $this->loadHomes();
    }

    /**
     * Store all home data in $homes property
     *
     * @return void
     */
    private function loadHomes() : void
    {
        Loader::getDatabase()->executeSelect(QueryStmts::SERVER_HOMES_SELECT, [], function (array $rows) : void {
            foreach ($rows as $row) {
                $uuid = (string) $row["uuid"];
                $this->maxHomesByUuid[$uuid] = max((int) ($this->maxHomesByUuid[$uuid] ?? $this->limit), (int) $row["max_homes"]);
                $this->homes[] = new Home(
                    Uuid::fromString($uuid),
                    $row["home_name"],
                    $row["world_name"],
                    $row["x"],
                    $row["y"],
                    $row["z"],
                    $row["max_homes"]
                );
            }
        });
    }

    /**
     * Create a home
     *
     * Example: HomeManager::createSession($player, "homeName1")
     *
     * @param Player $player
     * @param string $home_name
     */
    public function createHome(Player $player, string $home_name) : void
    {
        $home_name = trim($home_name);
        if ($home_name === "") {
            throw new \RuntimeException("Home name cannot be empty.");
        }

        $violation = FactionNameBlocklist::firstGenericNameViolation($home_name, $home_name, "home name");
        if ($violation !== null) {
            throw new \RuntimeException($violation);
        }

        $uuid = $player->getUniqueId()->toString();
        $currentHomes = $this->getHomeList($player->getUniqueId()) ?? [];
        if (count($currentHomes) >= $this->getMaxHomes($player->getUniqueId())) {
            throw new \RuntimeException("You have reached your max home limit.");
        }

        if ($this->getPlayerHome($player->getUniqueId(), $home_name) !== null) {
            throw new \RuntimeException("A home with that name already exists.");
        }

        $pos  = $player->getPosition();
        $args = [
            "uuid"       => $uuid,
            "home_name"  => $home_name,
            "world_name" => $player->getWorld()->getFolderName(),
            "x"          => $pos->getFloorX(),
            "y"          => $pos->getFloorY(),
            "z"          => $pos->getFloorZ(),
            "max_homes"      => $this->getMaxHomes($player->getUniqueId())
        ];

        Loader::getDatabase()->executeInsert(QueryStmts::SERVER_HOMES_CREATE, $args);

        $this->homes[] = new Home(
            $player->getUniqueId(),
            $args["home_name"],
            $args["world_name"],
            $args["x"],
            $args["y"],
            $args["z"],
            $args["max_homes"]
        );
    }

    /**
     * Get home by UUID & Home Name
     *
     * @param UuidInterface $uuid
     * @param string $home_name
     * @return Home|null
     */
    public function getPlayerHome(UuidInterface $uuid, string $home_name) : ?Home
    {
        foreach ($this->homes as $home) {
            # If the UUID does not match, skip to the next one
            if (!$home->getUuid()->equals($uuid)) {
                continue;
            }
            # If the name does not match, skip to the next one
            if ($home->getName() !== $home_name) {
                continue;
            }
            return $home;
        }
        return null;

        /*
         * This is a test version using array_filter. Same as foreach. I just found foreach more readable and cleaner-looking.
         *
        $filteredHomes = array_filter($this->homes, function($home) use ($uuid, $home_name) {
            return $home->getUuid()->equals($uuid) && $home->getName() === $home_name;
        });

        return $filteredHomes ? reset($filteredHomes) : null;
        */
    }

    /**
     * Get Home List by UUID
     *
     * @param UuidInterface $uuid
     * @return array|null
     */
    public function getHomeList(UuidInterface $uuid) : ?array
    {
        $fetched = [];
        foreach ($this->homes as $home) {
            # If the UUID does not match, skip to the next one
            if (!$home->getUuid()->equals($uuid)) {
                continue;
            }
            $fetched[] = $home;
        }

        # If the fetched array is empty, return null. Else, return fetched.
        return empty($fetched) ? null : $fetched;
    }

    /**
     * Delete a home
     *
     * @param Home $home
     * @return void
     */
    public function deleteHome(Home $home) : void
    {
        Loader::getDatabase()->executeChange(QueryStmts::SERVER_HOMES_DELETE, [
            "uuid" => $home->getUuid()->toString(),
            "home_name" => $home->getName()
        ]);

        unset($this->homes[array_search($home, $this->homes)]);
    }

    /**
     * Get a list of all homes registered on the server
     *
     * @return array
     */
    public function getHomes() : array
    {
        return $this->homes;
    }

    public function addMaxHomes(UuidInterface $uuid, int $amount = 1): void
    {
        $current = $this->getMaxHomes($uuid);
        $this->setMaxHomes($uuid, $current + $amount);
    }

    public function setMaxHomes(UuidInterface $uuid, int $amount = 5): void
    {
        $maxHomes = max(1, $amount);
        $this->maxHomesByUuid[$uuid->toString()] = $maxHomes;
        if (($session = $this->getSession($uuid)) !== null) {
            $session->setSetting("max_homes", $maxHomes);
        }
        $this->updateDb($uuid);
    }


    public function getMaxHomes(UuidInterface $uuid) : int {
        $maxHomes = $this->maxHomesByUuid[$uuid->toString()] ?? $this->limit;

        if (($session = $this->getSession($uuid)) !== null) {
            $setting = $session->getSetting("max_homes");
            if (is_int($setting) || is_float($setting) || (is_string($setting) && is_numeric($setting))) {
                $maxHomes = max($maxHomes, max(1, (int) $setting));
            }

            $player = $session->getPlayer();
            if ($player instanceof Player) {
                $maxHomes = max(
                    $maxHomes,
                    PermissionTierUtils::getHighestIntPermission($player, "glacia.home.limit.", $this->limit, 100)
                );
            }
        }

        return $maxHomes;
    }

    private function updateDb(UuidInterface $uuid): void
    {
        $maxHomes = $this->getMaxHomes($uuid);
        Loader::getDatabase()->executeChange(QueryStmts::SERVER_HOMES_UPDATE, [
            "uuid" => $uuid->toString(),
            "max_homes" => $maxHomes
        ]);

        foreach ($this->homes as $home) {
            if ($home->getUuid()->equals($uuid)) {
                $home->limit = $maxHomes;
            }
        }
    }

    private function getSession(UuidInterface $uuid) : ?GlaciaPlayer
    {
        return Loader::getPlayerManager()->getSessionByUuid($uuid);
    }

    public function resetSeasonState(): void
    {
        $this->homes = [];
        $this->maxHomesByUuid = [];
    }

}
