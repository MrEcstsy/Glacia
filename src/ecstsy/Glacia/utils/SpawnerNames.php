<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

final class SpawnerNames {

    private array $names;

    public function __construct() {
        $this->names = [
            10 => "chicken",
            11 => "cow",
            12 => "pig",
            17 => "squid",
            20 => "iron_golem",
            32 => "zombie",
            36 => "zombie_pigman",
            34 => "skeleton",
            37 => "slime",
            16 => "mooshroom",
            43 => "blaze",
        ];
    }

    /** @return array<int, string> */
    public function getNames(): array {
        return $this->names;
    }

    public function hasName(int $id): bool {
        return isset($this->names[$id]);
    }

    public function getName(int $id): string {
        return $this->names[$id] ?? 'Unknown';
    }

    public function setName(int $id, string $name): void {
        $this->names[$id] = $name;
    }
}