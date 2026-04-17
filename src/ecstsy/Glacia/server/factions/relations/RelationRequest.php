<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\relations;

final class RelationRequest
{
    public function __construct(
        public string $fromFactionId,
        public int $expiresAt
    )
    {
        
    }
}