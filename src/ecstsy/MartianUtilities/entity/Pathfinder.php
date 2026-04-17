<?php

namespace ecstsy\MartianUtilities\entity;

use pocketmine\block\Slab;
use pocketmine\block\Stair;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class Pathfinder {

    public function __construct(
        private World $world
    ) {}

    /**
     * Finds a ground path for walking entities.
     *
     * @param Vector3 $start Block-aligned/floor position
     * @param Vector3 $goal Block-aligned/floor position
     * @param int $maxDistance Search radius limit
     * @return Vector3[]|null
     */
    public function findPath(Vector3 $start, Vector3 $goal, int $maxDistance = 24): ?array {
        $startNode = $this->findStandableNode($start);
        $goalNode = $this->findStandableNodeNear($goal, 2);

        if ($startNode === null || $goalNode === null) {
            return null;
        }

        $startKey = $this->toKey($startNode);
        $goalKey = $this->toKey($goalNode);

        $open = [$startKey => true];
        $closed = [];

        $cameFrom = [];
        $gScore = [$startKey => 0.0];
        $fScore = [$startKey => $this->heuristic($startNode, $goalNode)];
        $nodes = [$startKey => $startNode];

        $iterations = 0;
        $maxIterations = 800;

        while (!empty($open) && $iterations++ < $maxIterations) {
            $currentKey = $this->getLowestScoreKey($open, $fScore);
            $current = $nodes[$currentKey];

            if ($currentKey === $goalKey) {
                return $this->reconstructPath($cameFrom, $nodes, $currentKey);
            }

            unset($open[$currentKey]);
            $closed[$currentKey] = true;

            foreach ($this->getNeighbors($current) as $neighbor) {
                if ($neighbor->distanceSquared($startNode) > ($maxDistance * $maxDistance)) {
                    continue;
                }

                $neighborKey = $this->toKey($neighbor);
                if (isset($closed[$neighborKey])) {
                    continue;
                }

                $tentativeG = $gScore[$currentKey] + $this->moveCost($current, $neighbor);

                if (!isset($open[$neighborKey]) || $tentativeG < ($gScore[$neighborKey] ?? PHP_FLOAT_MAX)) {
                    $cameFrom[$neighborKey] = $currentKey;
                    $gScore[$neighborKey] = $tentativeG;
                    $fScore[$neighborKey] = $tentativeG + $this->heuristic($neighbor, $goalNode);
                    $nodes[$neighborKey] = $neighbor;
                    $open[$neighborKey] = true;
                }
            }
        }

        return null;
    }

    /**
     * @return Vector3[]
     */
    private function reconstructPath(array $cameFrom, array $nodes, string $currentKey): array {
        $path = [$nodes[$currentKey]];

        while (isset($cameFrom[$currentKey])) {
            $currentKey = $cameFrom[$currentKey];
            array_unshift($path, $nodes[$currentKey]);
        }

        array_shift($path); // remove current/start node
        return $path;
    }

    private function getLowestScoreKey(array $open, array $fScore): string {
        $bestKey = array_key_first($open);
        $bestScore = $fScore[$bestKey] ?? PHP_FLOAT_MAX;

        foreach ($open as $key => $_) {
            $score = $fScore[$key] ?? PHP_FLOAT_MAX;
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestKey = $key;
            }
        }

        return $bestKey;
    }

    /**
     * Horizontal-only neighbors, with step-up / step-down resolving Y.
     *
     * @return Vector3[]
     */
    private function getNeighbors(Vector3 $node): array {
        $neighbors = [];

        $dirs = [
            [1, 0], [-1, 0], [0, 1], [0, -1],
            [1, 1], [1, -1], [-1, 1], [-1, -1],
        ];

        foreach ($dirs as [$dx, $dz]) {
            $candidate = $this->resolveWalkNode((int) $node->x + $dx, (int) $node->y, (int) $node->z + $dz);
            if ($candidate === null) {
                continue;
            }

            // no diagonal corner cutting through 2 solid sides
            if ($dx !== 0 && $dz !== 0) {
                $sideA = $this->resolveWalkNode((int) $node->x + $dx, (int) $node->y, (int) $node->z);
                $sideB = $this->resolveWalkNode((int) $node->x, (int) $node->y, (int) $node->z + $dz);

                if ($sideA === null && $sideB === null) {
                    continue;
                }
            }

            $neighbors[] = $candidate;
        }

        return $neighbors;
    }

    private function moveCost(Vector3 $from, Vector3 $to): float {
        $dx = abs($from->x - $to->x);
        $dz = abs($from->z - $to->z);

        $cost = ($dx === 1 && $dz === 1) ? 1.414 : 1.0;

        $dy = $to->y - $from->y;
        if ($dy > 0) {
            $cost += 0.35;
        } elseif ($dy < 0) {
            $cost += 0.15;
        }

        return $cost;
    }

    private function heuristic(Vector3 $a, Vector3 $b): float {
        $dx = $a->x - $b->x;
        $dz = $a->z - $b->z;
        return sqrt(($dx * $dx) + ($dz * $dz));
    }

    private function toKey(Vector3 $vec): string {
        return (int) $vec->x . ':' . (int) $vec->y . ':' . (int) $vec->z;
    }

    private function findStandableNode(Vector3 $pos): ?Vector3 {
        $x = $pos->getFloorX();
        $y = $pos->getFloorY();
        $z = $pos->getFloorZ();

        for ($dy = -2; $dy <= 2; $dy++) {
            $node = $this->resolveWalkNode($x, $y + $dy, $z);
            if ($node !== null) {
                return $node;
            }
        }

        return null;
    }

    private function findStandableNodeNear(Vector3 $pos, int $radius): ?Vector3 {
        $center = $this->findStandableNode($pos);
        if ($center !== null) {
            return $center;
        }

        $x = $pos->getFloorX();
        $y = $pos->getFloorY();
        $z = $pos->getFloorZ();

        for ($r = 1; $r <= $radius; $r++) {
            for ($dx = -$r; $dx <= $r; $dx++) {
                for ($dz = -$r; $dz <= $r; $dz++) {
                    $node = $this->findStandableNode(new Vector3($x + $dx, $y, $z + $dz));
                    if ($node !== null) {
                        return $node;
                    }
                }
            }
        }

        return null;
    }

    private function resolveWalkNode(int $x, int $baseY, int $z): ?Vector3 {
        // same level
        if ($this->isStandableAt($x, $baseY, $z)) {
            return new Vector3($x, $baseY, $z);
        }

        // step up 1
        if ($this->isStandableAt($x, $baseY + 1, $z)) {
            return new Vector3($x, $baseY + 1, $z);
        }

        // step down 1
        if ($this->isStandableAt($x, $baseY - 1, $z)) {
            return new Vector3($x, $baseY - 1, $z);
        }

        return null;
    }

    private function isStandableAt(int $x, int $y, int $z): bool {
        $feet = $this->world->getBlockAt($x, $y, $z);
        $head = $this->world->getBlockAt($x, $y + 1, $z);
        $ground = $this->world->getBlockAt($x, $y - 1, $z);

        if ($feet->isSolid() || $head->isSolid()) {
            return false;
        }

        if (!$ground->isSolid() && !($ground instanceof Slab) && !($ground instanceof Stair)) {
            return false;
        }

        return true;
    }
}