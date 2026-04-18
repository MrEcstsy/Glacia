<?php

declare(strict_types=1);

namespace ecstsy\Glacia\tasks;

use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\Position;
use pocketmine\world\sound\EndermanTeleportSound;

final class ChunkBusterTask extends Task {

    private const PRIME_TICKS = 30;
    private const LAYERS_PER_TICK = 4;

    /** @var array<string, true> */
    private static array $activeChunks = [];

    private int $primeTicks = self::PRIME_TICKS;
    private int $currentY;
    private int $lastProgressStep = -1;
    private bool $beaconRemoved = false;
    private string $chunkKey;

    public function __construct(
        private Player $owner,
        private Position $position,
        private int $chunkX,
        private int $chunkZ
    ) {
        $this->currentY = $position->getWorld()->getMaxY() - 1;
        $this->chunkKey = self::makeChunkKey($position->getWorld()->getFolderName(), $chunkX, $chunkZ);
        self::$activeChunks[$this->chunkKey] = true;
    }

    public static function isChunkActive(string $worldName, int $chunkX, int $chunkZ): bool
    {
        return isset(self::$activeChunks[self::makeChunkKey($worldName, $chunkX, $chunkZ)]);
    }

    public function onRun(): void
    {
        $world = $this->position->getWorld();
        if ($this->primeTicks > 0) {
            $this->runPrimeTick();
            return;
        }

        if (!$this->beaconRemoved) {
            $world->setBlock($this->position, VanillaBlocks::AIR());
            $this->beaconRemoved = true;
        }

        $startX = $this->chunkX << 4;
        $endX = $startX + 15;
        $startZ = $this->chunkZ << 4;
        $endZ = $startZ + 15;

        for ($layer = 0; $layer < self::LAYERS_PER_TICK && $this->currentY > 0; $layer++, $this->currentY--) {
            for ($x = $startX; $x <= $endX; $x++) {
                for ($z = $startZ; $z <= $endZ; $z++) {
                    $block = $world->getBlockAt($x, $this->currentY, $z);
                    if ($block->isSameState(VanillaBlocks::AIR()) || $block->isSameState(VanillaBlocks::BEDROCK())) {
                        continue;
                    }

                    $world->setBlockAt($x, $this->currentY, $z, VanillaBlocks::AIR());
                }
            }
        }

        $center = $this->position->add(0.5, max(1, $this->currentY), 0.5);
        $this->addRingParticles($center, 2.25, 8);
        for ($i = 0; $i < 5; $i++) {
            $world->addParticle($center->add(mt_rand(-1, 1), mt_rand(-2, 2), mt_rand(-1, 1)), new PortalParticle());
        }

        if ($this->currentY % 3 === 0) {
            $world->addSound($center, new EndermanTeleportSound());
        }

        if ($this->owner->isConnected() && $this->currentY % 3 === 0) {
            PlayerUtils::playSound($this->owner, "random.orb", 1.0, 1.1);
        }

        $progressStep = (int) floor((($world->getMaxY() - max(0, $this->currentY)) / max(1, $world->getMaxY())) * 8);
        if ($this->owner->isConnected() && $progressStep !== $this->lastProgressStep) {
            $this->lastProgressStep = $progressStep;
            $percent = min(100, max(0, (int) round((($world->getMaxY() - max(0, $this->currentY)) / max(1, $world->getMaxY())) * 100)));
            $this->owner->sendTip(C::colorize("&r&b&lCHUNK BUSTER &8» &r&7Clearing chunk... &f{$percent}%"));
        }

        if ($this->currentY <= 0) {
            $world->addParticle($this->position->add(0.5, 1, 0.5), new HugeExplodeParticle());
            if ($this->owner->isConnected()) {
                $this->owner->sendMessage(C::colorize("&r&b&lCHUNK BUSTER &8» &r&aThis chunk has been fully cleared."));
                PlayerUtils::playSound($this->owner, "random.levelup");
            }
            $this->stop();
        }
    }

    private function runPrimeTick(): void
    {
        $world = $this->position->getWorld();
        $center = $this->position->add(0.5, 1, 0.5);

        $this->addRingParticles($center, 1.2 + ((self::PRIME_TICKS - $this->primeTicks) * 0.03), 6);
        for ($i = 0; $i < 4; $i++) {
            $world->addParticle($center->add(mt_rand(-1, 1), mt_rand(0, 2), mt_rand(-1, 1)), new PortalParticle());
        }

        if ($this->owner->isConnected()) {
            PlayerUtils::playSound($this->owner, "random.fuse", 1.0, 1.0 + ((self::PRIME_TICKS - $this->primeTicks) * 0.02));
            if ($this->primeTicks === 30 || $this->primeTicks === 20 || $this->primeTicks === 10 || $this->primeTicks === 1) {
                $seconds = max(1, (int) ceil($this->primeTicks / 20));
                $this->owner->sendTip(C::colorize("&r&b&lCHUNK BUSTER &8» &r&7Priming... &f{$seconds}s"));
            }
        }

        $this->primeTicks--;
    }

    private function addRingParticles(Vector3 $center, float $radius, int $points): void
    {
        $world = $this->position->getWorld();
        for ($i = 0; $i < $points; $i++) {
            $angle = (M_PI * 2 * $i) / $points;
            $x = cos($angle) * $radius;
            $z = sin($angle) * $radius;
            $world->addParticle($center->add($x, 0, $z), new PortalParticle());
        }
    }

    private function stop(): void
    {
        unset(self::$activeChunks[$this->chunkKey]);
        $this->getHandler()?->cancel();
    }

    private static function makeChunkKey(string $worldName, int $chunkX, int $chunkZ): string
    {
        return "{$worldName}:{$chunkX}:{$chunkZ}";
    }
}
