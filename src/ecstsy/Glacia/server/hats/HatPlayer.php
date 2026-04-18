<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\hats;

use pocketmine\player\Player;

final class HatPlayer extends Player {

    private ?RenderedBlockHat $renderedHat = null;

    public function getRenderedHat(): ?RenderedBlockHat
    {
        return $this->renderedHat;
    }

    public function setRenderedHat(?RenderedBlockHat $hat): void
    {
        if ($this->renderedHat !== null) {
            $this->renderedHat->removeFrom($this, ...$this->getViewers());
        }

        $this->renderedHat = $hat;

        if ($hat !== null) {
            $hat->sendTo($this, ...$this->getViewers());
        }
    }

    protected function sendSpawnPacket(Player $player): void
    {
        parent::sendSpawnPacket($player);
        $this->renderedHat?->sendTo($player);
    }

    public function despawnFrom(Player $player, bool $send = true): void
    {
        parent::despawnFrom($player, $send);
        if ($send) {
            $this->renderedHat?->removeFrom($player);
        }
    }
}
