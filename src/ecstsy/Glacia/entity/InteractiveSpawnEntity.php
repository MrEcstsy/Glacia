<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use pocketmine\player\Player;

interface InteractiveSpawnEntity
{
    public function getInteractAction(): string;

    public function setInteractAction(string $action): void;

    public function getDisplayLabel(): string;

    public function setDisplayLabel(string $label): void;

    public function shiftAnchorY(float $delta): void;

    public function shiftHologramYOffset(float $delta): void;

    public function getHologramYOffset(): float;

    public function relocateAnchorTo(Player $player, float $yOffset = 0.0): void;
}
