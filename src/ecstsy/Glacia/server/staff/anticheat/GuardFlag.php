<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat;

/**
 * Observed signal from an optional Guard module (confidence-based, log-only).
 *
 * @phpstan-type Evidence array<string, mixed>
 */
final class GuardFlag
{
    /**
     * @param Evidence $evidenceSnapshot
     */
    public function __construct(
        public string $type,
        public float $confidence,
        public string $detail,
        public array $evidenceSnapshot = []
    ) {
    }

    public function withConfidence(float $confidence): self
    {
        return new self($this->type, $confidence, $this->detail, $this->evidenceSnapshot);
    }
}
