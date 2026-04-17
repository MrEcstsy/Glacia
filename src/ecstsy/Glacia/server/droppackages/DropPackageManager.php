<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\droppackages;

use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;

final class DropPackageManager {
    use SingletonTrait;

    /** @var array<string, DropPackageSession> */
    private array $sessions = [];

    public function __construct(){
        self::setInstance($this);
    }

    public function createSession(Player $player, string $packageType, string $displayName, string $title, int $pickAmount = 5): DropPackageSession{
        $session = new DropPackageSession($packageType, $displayName, $title, $pickAmount);
        $this->sessions[strtolower($player->getName())] = $session;
        return $session;
    }

    public function getSession(Player $player): ?DropPackageSession{
        return $this->sessions[strtolower($player->getName())] ?? null;
    }

    public function removeSession(Player $player): void{
        unset($this->sessions[strtolower($player->getName())]);
    }
}