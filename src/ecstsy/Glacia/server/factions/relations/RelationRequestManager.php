<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\relations;

use pocketmine\utils\SingletonTrait;

final class RelationRequestManager
{
    use SingletonTrait;

    private const REQUEST_TIMEOUT = 120; 

    private array $allyRequests = [];

    public function __construct()
    {
        self::setInstance($this);
    }

    public function addRequest(string $from, string $to): void {
        $this->allyRequests[$to][$from] = time() + self::REQUEST_TIMEOUT; 
    }

    public function getRequest(string $to): ?RelationRequest {
        if (!isset($this->allyRequests[$to])) return null;

        foreach ($this->allyRequests[$to] as $from => $expires) {
            if ($expires > time()) {
                return new RelationRequest($from, $expires);
            }
        }

        return null;
    }

    public function removeRequest(string $to, string $from): void {
        unset($this->allyRequests[$to][$from]);
    }

    public function hasRequestFrom(string $to, string $from): bool {
        return isset($this->allyRequests[$to][$from]) && $this->allyRequests[$to][$from] > time();
    }
}