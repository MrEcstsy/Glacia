<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server;

use ecstsy\Glacia\entity\CombatLoggerEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class CombatManagerInstance {

    /** 
     * @var array<string, array{
     *   expires: float,
     *   expireSec: int,
     *   npc: ?CombatLoggerEntity,
     *   inventory: array,
     *   armor: array,
     *   deadOnLogin: bool,
     *   opponents: array<string,string>
     * }>
     */
    private array $combatPlayers = [];

    /** expiry second => list of lower names */
    private array $expiryBuckets = [];

    /** lowerName => Player (kept via join/quit events) */
    private array $onlineByLower = [];

    private float $combatTime = 15.0;

    public function __construct(float $combatTime = 15.0) {
        $this->combatTime = max(1.0, $combatTime);

        $this->loadCombatLoggers();
    }

    private function loadCombatLoggers(): void {
    Loader::getDatabase()->executeSelect(QueryStmts::COMBAT_LOGGER_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $lower = strtolower($row["username"]);

                $this->combatPlayers[$lower] = [
                    'expires' => (float)$row["expires"],
                    'expireSec' => (int)$row["expires"],
                    'npc' => null,
                    'inventory' => $this->deserializeItems(json_decode($row["inventory"], true) ?? []),
                    'armor' => $this->deserializeItems(json_decode($row["armor"], true) ?? []),
                    'deadOnLogin' => (bool)$row["dead_on_login"],
                    'opponents' => [],
                    'xp' => (int)$row["xp"],
                    'health' => (float)$row["health"],
                    'location' => [
                        'world' => $row["world"],
                        'x' => $row["x"],
                        'y' => $row["y"],
                        'z' => $row["z"],
                        'yaw' => $row["yaw"],
                        'pitch' => $row["pitch"],
                    ]
                ];
            }
        });
    }

    public function setCombatTime(float $seconds): void {
        $this->combatTime = max(1.0, $seconds);
    }

    public function getBaseCombatTime(): float
    {
        return $this->combatTime;
    }

    /**
     * Call on PlayerJoin event
     */
    public function register(Player $player): void {
        $this->onlineByLower[strtolower($player->getName())] = $player;
    }

    /**
     * Call on PlayerQuit event
     */
    public function unregister(Player $player): void {
        unset($this->onlineByLower[strtolower($player->getName())]);
    }

    /**
     * Start or extend combat for $player. Snapshot inventory only when the player first enters combat.
     */
    public function startCombat(Player $player, ?Player $opponent = null): void {
        $lower = strtolower($player->getName());
        $now = microtime(true);
        $expires = $now + $this->getCombatTimeFor($player);
        $expireSec = (int)ceil($expires);

        $already = isset($this->combatPlayers[$lower]) && $this->combatPlayers[$lower]['expires'] > $now;

        if (!$already) {
            $invContents = $player->getInventory()->getContents();
            $armorContents = $player->getArmorInventory()->getContents();

            $this->combatPlayers[$lower] = [
                'expires' => $expires,
                'expireSec' => $expireSec,
                'npc' => null,
                'inventory' => $invContents,
                'armor' => $armorContents,
                'deadOnLogin' => false,
                'opponents' => [],
            ];

            $this->expiryBuckets[$expireSec][] = $lower;

            $player->sendMessage(C::colorize("&r&c&l(!) &r&cYou have entered combat!"));
        } else {
            $prevSec = $this->combatPlayers[$lower]['expireSec'];
            $this->removeFromBucket($prevSec, $lower);

            $this->combatPlayers[$lower]['expires'] = $expires;
            $this->combatPlayers[$lower]['expireSec'] = $expireSec;
            $this->expiryBuckets[$expireSec][] = $lower;
        }

        if ($opponent !== null) {
            $opLower = strtolower($opponent->getName());
            $this->combatPlayers[$lower]['opponents'][$opLower] = $opponent->getName();

            if (!isset($this->combatPlayers[$opLower]) || $this->combatPlayers[$opLower]['expires'] <= $now) {
                $this->startCombat($opponent, $player);
            } else {
                $this->combatPlayers[$opLower]['opponents'][$lower] = $player->getName();
                $prevSec = $this->combatPlayers[$opLower]['expireSec'];
                $this->removeFromBucket($prevSec, $opLower);

                $opponentExpires = $now + $this->getCombatTimeFor($opponent);
                $opponentExpireSec = (int) ceil($opponentExpires);
                $this->combatPlayers[$opLower]['expires'] = $opponentExpires;
                $this->combatPlayers[$opLower]['expireSec'] = $opponentExpireSec;
                $this->expiryBuckets[$opponentExpireSec][] = $opLower;
            }
        }
    }

    private function getCombatTimeFor(Player $player): float
    {
        $faction = Loader::getPlayerManager()->getSession($player)?->getFaction();
        $reduction = Loader::getFactionUpgradeManager()->getCombatTagReductionSeconds($faction);
        return max(1.0, $this->combatTime - $reduction);
    }

    private function removeFromBucket(int $sec, string $lower): void {
        if (!isset($this->expiryBuckets[$sec])) return;
        $idx = array_search($lower, $this->expiryBuckets[$sec], true);
        if ($idx !== false) {
            array_splice($this->expiryBuckets[$sec], $idx, 1);
            if (empty($this->expiryBuckets[$sec])) {
                unset($this->expiryBuckets[$sec]);
            }
        }
    }

    public function isInCombat(Player $player): bool {
        $lower = strtolower($player->getName());
        $now = microtime(true);
        return isset($this->combatPlayers[$lower]) && $this->combatPlayers[$lower]['expires'] > $now;
    }

    public function handleLogout(Player $player): void {
        $lower = strtolower($player->getName());

        if (!isset($this->combatPlayers[$lower])) {
            return;
        }

        if ($this->combatPlayers[$lower]['expires'] <= microtime(true)) {
            $sec = $this->combatPlayers[$lower]['expireSec'];
            $this->removeFromBucket($sec, $lower);
            unset($this->combatPlayers[$lower]);
            return;
        }

        $this->combatPlayers[$lower]['inventory'] = $player->getInventory()->getContents();
        $this->combatPlayers[$lower]['armor'] = $player->getArmorInventory()->getContents();
        $this->combatPlayers[$lower]['xp'] = $player->getXpManager()->getCurrentTotalXp();
        $this->combatPlayers[$lower]['health'] = $player->getHealth();
        $this->combatPlayers[$lower]['location'] = [
            'world' => $player->getWorld()->getFolderName(),
            'x' => $player->getPosition()->getX(),
            'y' => $player->getPosition()->getY(),
            'z' => $player->getPosition()->getZ(),
            'yaw' => $player->getLocation()->getYaw(),
            'pitch' => $player->getLocation()->getPitch(),
        ];

        Loader::getDatabase()->executeInsert(QueryStmts::COMBAT_LOGGER_CREATE, [
            "uuid" => $player->getUniqueId()->toString(),
            "username" => $player->getName(),
            "world" => $player->getWorld()->getFolderName(),
            "x" => $player->getPosition()->getX(),
            "y" => $player->getPosition()->getY(),
            "z" => $player->getPosition()->getZ(),
            "yaw" => $player->getLocation()->getYaw(),
            "pitch" => $player->getLocation()->getPitch(),
            "inventory" => json_encode($this->serializeItems($player->getInventory()->getContents())),
            "armor" => json_encode($this->serializeItems($player->getArmorInventory()->getContents())),
            "xp" => $player->getXpManager()->getCurrentTotalXp(),
            "health" => $player->getHealth(),
            "expires" => time() + (int)$this->combatTime
        ]);

        $npc = new CombatLoggerEntity($player->getLocation());
        $npc->populateFromPlayer($player);
        $npc->setStoredXp($player->getXpManager()->getCurrentTotalXp());
        $npc->spawnToAll();

        $this->combatPlayers[$lower]['npc'] = $npc;

        // DO NOT CLEAR PLAYER INVENTORY / ARMOR HERE
    }

    private function serializeItems(array $items): array {
        $data = [];

        foreach ($items as $slot => $item) {
            if ($item instanceof Item && !$item->isNull()) {
                $data[$slot] = ItemUtils::encodeItem($item);
            }
        }

        return $data;
    }

    private function deserializeItems(array $data): array {
        $items = [];

        foreach ($data as $slot => $encoded) {
            $item = ItemUtils::decodeItem($encoded);

            if ($item instanceof Item && !$item->isNull()) {
                $items[(int) $slot] = $item;
            }
        }

        return $items;
    }

    public function handleLogin(Player $player): void {
        $lower = strtolower($player->getName());

        if (!isset($this->combatPlayers[$lower])) {
            return;
        }

        $data = $this->combatPlayers[$lower];

        if (($data['deadOnLogin'] ?? false) === true) {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->sendMessage(C::colorize("&r&cYou died while logged out in combat!"));

            $sec = $data['expireSec'];
            Loader::getDatabase()->executeChange(QueryStmts::COMBAT_LOGGER_DELETE, [
                "uuid" => $player->getUniqueId()->toString()
            ]);
            $this->removeFromBucket($sec, $lower);
            unset($this->combatPlayers[$lower]);
            return;
        }

        $npc = $data['npc'] ?? null;

        if ($npc instanceof CombatLoggerEntity && !$npc->isClosed()) {
            $player->teleport($npc->getLocation());
            $player->setHealth(min($player->getMaxHealth(), $npc->getHealth()));
            $npc->flagForDespawn();
        } elseif (isset($data['location']) && is_array($data['location'])) {
            $loc = $data['location'];
            $world = Server::getInstance()->getWorldManager()->getWorldByName($loc['world']);

            if ($world !== null) {
                $player->teleport(new Location(
                    (float) $loc['x'],
                    (float) $loc['y'],
                    (float) $loc['z'],
                    $world,
                    (float) $loc['yaw'],
                    (float) $loc['pitch']
                ));
            }

            if (isset($data['health'])) {
                $player->setHealth(min($player->getMaxHealth(), (float) $data['health']));
            }
        }

        $player->getInventory()->setContents($data['inventory'] ?? []);
        $player->getArmorInventory()->setContents($data['armor'] ?? []);

        if (isset($data['xp'])) {
            $player->getXpManager()->setCurrentTotalXp((int) $data['xp']);
        }

        $player->sendMessage(C::colorize("&r&aYou survived combat logging!"));

        Loader::getDatabase()->executeChange(QueryStmts::COMBAT_LOGGER_DELETE, [
            "uuid" => $player->getUniqueId()->toString()
        ]);

        $this->removeFromBucket($data['expireSec'], $lower);
        unset($this->combatPlayers[$lower]);
    }

    public function handleNPCDeath(CombatLoggerEntity $npc): void {
        $lower = strtolower($npc->getOwnerName());
        $data = $this->combatPlayers[$lower] ?? null;

        $items = $npc->getStoredInventory();
        if (empty($items) && $data !== null && !empty($data['inventory'])) {
            $items = $data['inventory'];
        }

        foreach ($items as $item) {
            if ($item instanceof Item && !$item->isNull()) {
                $npc->getWorld()->dropItem($npc->getPosition(), clone $item);
            }
        }

        $armor = $npc->getStoredArmor();
        if (empty($armor) && $data !== null && !empty($data['armor'])) {
            $armor = $data['armor'];
        }

        foreach ($armor as $item) {
            if ($item instanceof Item && !$item->isNull()) {
                $npc->getWorld()->dropItem($npc->getPosition(), clone $item);
            }
        }

        $xp = $npc->getStoredXp();
        if ($xp > 0) {
            $npc->getWorld()->dropItem($npc->getPosition(), GlaciaItemFactory::xpNote(null, $xp));
        }

        if ($data !== null) {
            $this->combatPlayers[$lower]['deadOnLogin'] = true;
            $this->combatPlayers[$lower]['npc'] = null;
        }

        $ownerUuid = $npc->getOwnerUUID();
        if ($ownerUuid !== "") {
            Loader::getDatabase()->executeChange(QueryStmts::COMBAT_LOGGER_UPDATE_DEAD, [
                "uuid" => $ownerUuid
            ]);
        }
    }

    /**
     * O( #expiredThisSecond ) each tick (very cheap if not many expire at once)
     * Call every 1s.
     */
    public function tick(): void {
        $now = microtime(true);
        $nowSec = (int)floor($now);

        $toProcess = $this->expiryBuckets[$nowSec] ?? [];
        unset($this->expiryBuckets[$nowSec]);

        foreach ($toProcess as $lower) {
            $entry = $this->combatPlayers[$lower] ?? null;
            if ($entry === null) {
                continue;
            }

            if (($entry['deadOnLogin'] ?? false) === true) {
                continue;
            }

            if ($entry['expires'] > $now) {
                $newSec = $entry['expireSec'];
                $this->expiryBuckets[$newSec][] = $lower;
                continue;
            }

            $this->expireCombatFor($lower);
        }
    }

    private function expireCombatFor(string $lower): void {
        $entry = $this->combatPlayers[$lower] ?? null;
        if ($entry === null) {
            return;
        }

        $player = $this->getOnlinePlayerByLowerName($lower);
        if ($player !== null) {
            $player->sendMessage(C::colorize("&r&aYou are no longer in combat. You may now safely logout."));
        }

        foreach ($entry['opponents'] ?? [] as $oppLower => $storedName) {
            $opp = $this->getOnlinePlayerByLowerName($oppLower);
            if ($opp !== null) {
                $opp->sendMessage(C::colorize("&r&aYou are no longer in combat. You may now safely logout."));
            }
        }

        $npc = $entry['npc'] ?? null;
        if ($npc instanceof CombatLoggerEntity && !$npc->isClosed()) {
            $npc->flagForDespawn();
        }

        unset($this->combatPlayers[$lower]);
    }

    private function getOnlinePlayerByLowerName(string $lower): ?Player {
        $cached = $this->onlineByLower[$lower] ?? null;

        if ($cached instanceof Player && !$cached->isClosed() && $cached->isConnected()) {
            return $cached;
        }

        unset($this->onlineByLower[$lower]);

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if (strtolower($player->getName()) === $lower) {
                $this->onlineByLower[$lower] = $player;
                return $player;
            }
        }

        return null;
    }

    public function getPlayersInCombat(): array {
        $out = [];
        foreach (array_keys($this->combatPlayers) as $lower) {
            $p = $this->getOnlinePlayerByLowerName($lower);
            if ($p !== null) $out[] = $p;
        }
        return $out;
    }

    public function hasCombatEntry(Player $player): bool {
        return isset($this->combatPlayers[strtolower($player->getName())]);
    }
}
