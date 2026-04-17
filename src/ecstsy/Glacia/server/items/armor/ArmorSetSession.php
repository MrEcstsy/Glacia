<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\armor;

final class ArmorSetSession {

    public function __construct(
        private ?string $activeSet = null
    ) {}

    public function getActiveSet(): ?string {
        return $this->activeSet;
    }

    public function setActiveSet(?string $set): void {
        $this->activeSet = $set;
    }
}