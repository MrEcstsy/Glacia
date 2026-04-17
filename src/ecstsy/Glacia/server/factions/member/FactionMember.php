<?php
declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\member;

use Ramsey\Uuid\UuidInterface;

final class FactionMember {
    public function __construct(
        private UuidInterface $uuid,
        private string $role,
        private int $joinedAt
    ) {}

    public function getUuid(): UuidInterface {
        return $this->uuid;
    }

    public function getRole(): string {
        return $this->role;
    }

    public function setRole(string $role): void {
        $this->role = $role;
    }

    public function getJoinedAt(): int {
        return $this->joinedAt;
    }

    public function toArray(): array {
        return [
            'uuid' => $this->uuid,
            'role' => $this->role,
            'joined_at' => $this->joinedAt
        ];
    }
}
