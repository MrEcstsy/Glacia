<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\invites;

use pocketmine\utils\SingletonTrait;
use Ramsey\Uuid\UuidInterface;

final class FactionInviteManager
{
    use SingletonTrait;

    private const INVITE_TIME = 300;

    /** @var array<string> */
    private array $invites = [];

    public function __construct()
    {
        self::setInstance($this);
    }

    public function invitePlayer(UuidInterface $uuid, string $factionId): void
    {
        $uuidString = $uuid->toString();
        
        $this->invites[$uuidString][$factionId] = time() + self::INVITE_TIME;
    }

    public function getInviteTime(UuidInterface $uuid, string $factionId): int {
        $uuidString = $uuid->toString();

        if (!isset($this->invites[$uuidString][$factionId])) {
            return 0;
        }

        $expiresAt = $this->invites[$uuidString][$factionId];
        $remaining = $expiresAt - time();

        return $remaining > 0 ? $remaining : 0;
    }

    public function hasInvite(UuidInterface $uuid, string $factionId): bool
    {
        $uuidString = $uuid->toString();

        if (!isset($this->invites[$uuidString][$factionId])) {
            return false;
        }

        if ($this->invites[$uuidString][$factionId] < time()) {
            unset($this->invites[$uuidString][$factionId]);

            if (empty($this->invites[$uuidString])) {
                unset($this->invites[$uuidString]);
            }
            return false;
        }

        return true;
    }

    public function revokeInvite(UuidInterface $uuid, string $factionId): void
    {
        $uuidString = $uuid->toString();
        unset($this->invites[$uuidString][$factionId]);

        if (empty($this->invites[$uuidString])) {
            unset($this->invites[$uuidString]);
        }
    }
}
