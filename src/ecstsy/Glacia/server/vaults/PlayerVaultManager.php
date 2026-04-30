<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\vaults;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class PlayerVaultManager {

    private const MAX_PERMISSION_SCAN = 100;

    /** @var array<string, array<int, array<int, string>>> */
    private array $vaults = [];

    /** @var array<string, string> */
    private array $openEditors = [];
    private bool $dirty = false;

    public function __construct()
    {
        $this->load();
    }

    private function load() : void
    {
        Loader::getDatabase()->executeSelect(QueryStmts::PLAYER_VAULTS_SELECT, [], function(array $rows) : void {
            foreach ($rows as $row) {
                $ownerUuid = (string) $row["owner_uuid"];
                $vaultNumber = (int) $row["vault_number"];
                $contents = StorageUtils::decodeJsonString((string) $row["contents"], "player vault {$ownerUuid}:{$vaultNumber}");
                if (!is_array($contents)) {
                    $contents = [];
                }

                $normalized = [];
                foreach ($contents as $slot => $encodedItem) {
                    $slot = (int) $slot;
                    if ($slot < 0 || $slot >= 54 || !is_string($encodedItem) || $encodedItem === "") {
                        continue;
                    }
                    $normalized[$slot] = $encodedItem;
                }

                $this->vaults[$ownerUuid][$vaultNumber] = $normalized;
            }
        });
    }

    public function canAccessVault(Player $player, int $vaultNumber, bool $adminOverride = false) : bool
    {
        if ($vaultNumber <= 0) {
            return false;
        }

        if ($adminOverride) {
            return true;
        }

        return $player->hasPermission("glacia.vault.*")
            || $player->hasPermission("glacia.vault.infinite")
            || $player->hasPermission("glacia.vault." . $vaultNumber);
    }

    public function getAccessibleVaults(Player $player) : array
    {
        if ($this->hasInfiniteAccess($player)) {
            $numbers = array_keys($this->vaults[$player->getUniqueId()->toString()] ?? []);
            sort($numbers);
            return $numbers;
        }

        $accessible = [];
        for ($i = 1; $i <= self::MAX_PERMISSION_SCAN; $i++) {
            if ($player->hasPermission("glacia.vault." . $i)) {
                $accessible[] = $i;
            }
        }

        return $accessible;
    }

    public function hasInfiniteAccess(Player $player) : bool
    {
        return $player->hasPermission("glacia.vault.*") || $player->hasPermission("glacia.vault.infinite");
    }

    public function getVaultContents(string $ownerUuid, int $vaultNumber) : array
    {
        $decoded = [];
        foreach ($this->vaults[$ownerUuid][$vaultNumber] ?? [] as $slot => $encodedItem) {
            try {
                $decoded[(int) $slot] = ItemUtils::decodeItem($encodedItem);
            } catch (\Throwable) {
                unset($this->vaults[$ownerUuid][$vaultNumber][(int) $slot]);
                $this->dirty = true;
                continue;
            }
        }

        $this->persistIfDirty();
        ksort($decoded);
        return $decoded;
    }

    public function populateInventory(Inventory $inventory, string $ownerUuid, int $vaultNumber) : void
    {
        $inventory->clearAll();
        foreach ($this->getVaultContents($ownerUuid, $vaultNumber) as $slot => $item) {
            $inventory->setItem($slot, $item);
        }
    }

    public function claimOpenEditor(string $ownerUuid, int $vaultNumber, Player $viewer) : bool
    {
        $key = $this->getVaultKey($ownerUuid, $vaultNumber);
        $viewerUuid = $viewer->getUniqueId()->toString();

        if (isset($this->openEditors[$key])) {
            $currentViewerUuid = $this->openEditors[$key];
            if ($currentViewerUuid === $viewerUuid) {
                return true;
            }

            if ($this->isViewerOnline($currentViewerUuid)) {
                return false;
            }

            unset($this->openEditors[$key]);
        }

        $this->openEditors[$key] = $viewerUuid;
        return true;
    }

    public function releaseOpenEditor(string $ownerUuid, int $vaultNumber, Inventory $inventory) : void
    {
        $this->saveInventory($ownerUuid, $vaultNumber, $inventory);
        unset($this->openEditors[$this->getVaultKey($ownerUuid, $vaultNumber)]);
    }

    public function abortOpenEditor(string $ownerUuid, int $vaultNumber) : void
    {
        unset($this->openEditors[$this->getVaultKey($ownerUuid, $vaultNumber)]);
    }

    public function releaseViewerLocks(string $viewerUuid) : void
    {
        foreach ($this->openEditors as $key => $currentViewerUuid) {
            if ($currentViewerUuid === $viewerUuid) {
                unset($this->openEditors[$key]);
            }
        }
    }

    public function getOpenEditorCount() : int
    {
        return count($this->openEditors);
    }

    public function searchVaults(string $ownerUuid, array $vaultNumbers, string $query) : array
    {
        $needle = strtolower(trim(C::clean($query)));
        if ($needle === "") {
            return [];
        }

        $matches = [];
        foreach ($vaultNumbers as $vaultNumber) {
            $count = 0;
            foreach ($this->getVaultContents($ownerUuid, (int) $vaultNumber) as $item) {
                if ($this->itemMatchesQuery($item, $needle)) {
                    $count++;
                }
            }

            if ($count > 0) {
                $matches[(int) $vaultNumber] = $count;
            }
        }

        ksort($matches);
        return $matches;
    }

    public function resolvePlayerSession(string $name) : ?GlaciaPlayer
    {
        return Loader::getPlayerManager()->getSessionByPrefix($name);
    }

    public function getVaultItemCount(string $ownerUuid, int $vaultNumber) : int
    {
        return count($this->vaults[$ownerUuid][$vaultNumber] ?? []);
    }

    public function getKnownVaultNumbers(string $ownerUuid) : array
    {
        $numbers = array_keys($this->vaults[$ownerUuid] ?? []);
        sort($numbers);
        return $numbers;
    }

    public function getNonEmptyVaultNumbers(string $ownerUuid, ?Player $viewer = null) : array
    {
        $vaultNumbers = [];

        foreach ($this->getKnownVaultNumbers($ownerUuid) as $vaultNumber) {
            $vaultNumber = (int) $vaultNumber;
            if ($viewer !== null && !$this->canAccessVault($viewer, $vaultNumber)) {
                continue;
            }

            if ($this->getVaultItemCount($ownerUuid, $vaultNumber) > 0) {
                $vaultNumbers[] = $vaultNumber;
            }
        }

        return $vaultNumbers;
    }

    private function saveInventory(string $ownerUuid, int $vaultNumber, Inventory $inventory) : void
    {
        $contents = [];
        foreach ($inventory->getContents() as $slot => $item) {
            if ($item->isNull() || $item->getCount() <= 0) {
                continue;
            }

            $contents[(int) $slot] = ItemUtils::encodeItem($item);
        }

        if ($contents === []) {
            unset($this->vaults[$ownerUuid][$vaultNumber]);
            if (($this->vaults[$ownerUuid] ?? []) === []) {
                unset($this->vaults[$ownerUuid]);
            }

            Loader::getDatabase()->executeChange(QueryStmts::PLAYER_VAULTS_DELETE, [
                "owner_uuid" => $ownerUuid,
                "vault_number" => $vaultNumber
            ]);
            return;
        }

        ksort($contents);
        $this->vaults[$ownerUuid][$vaultNumber] = $contents;

        $encodedContents = StorageUtils::encodeJsonString($contents, "player vault {$ownerUuid}:{$vaultNumber}");
        if ($encodedContents === null) {
            return;
        }

        Loader::getDatabase()->executeInsert(QueryStmts::PLAYER_VAULTS_CREATE, [
            "owner_uuid" => $ownerUuid,
            "vault_number" => $vaultNumber,
            "contents" => $encodedContents,
            "updated_at" => time()
        ]);
    }

    private function itemMatchesQuery(Item $item, string $query) : bool
    {
        $parts = [
            C::clean($item->getName())
        ];

        if ($item->getCustomName() !== "") {
            $parts[] = C::clean($item->getCustomName());
        }

        foreach ($item->getLore() as $line) {
            $parts[] = C::clean($line);
        }

        $haystack = strtolower(implode("\n", $parts));
        return str_contains($haystack, $query);
    }

    private function getVaultKey(string $ownerUuid, int $vaultNumber) : string
    {
        return $ownerUuid . ":" . $vaultNumber;
    }

    private function persistIfDirty() : void
    {
        if (!$this->dirty) {
            return;
        }

        $removedVaults = [];
        foreach ($this->vaults as $ownerUuid => $vaults) {
            foreach ($vaults as $vaultNumber => $contents) {
                if ($contents === []) {
                    unset($this->vaults[$ownerUuid][$vaultNumber]);
                    $removedVaults[] = [$ownerUuid, (int) $vaultNumber];
                }
            }

            if (($this->vaults[$ownerUuid] ?? []) === []) {
                unset($this->vaults[$ownerUuid]);
            }
        }

        foreach ($removedVaults as [$ownerUuid, $vaultNumber]) {
            Loader::getDatabase()->executeChange(QueryStmts::PLAYER_VAULTS_DELETE, [
                "owner_uuid" => $ownerUuid,
                "vault_number" => $vaultNumber
            ]);
        }

        foreach ($this->vaults as $ownerUuid => $vaults) {
            foreach ($vaults as $vaultNumber => $contents) {
                $encodedContents = StorageUtils::encodeJsonString($contents, "player vault {$ownerUuid}:{$vaultNumber}");
                if ($encodedContents === null) {
                    continue;
                }

                Loader::getDatabase()->executeInsert(QueryStmts::PLAYER_VAULTS_CREATE, [
                    "owner_uuid" => $ownerUuid,
                    "vault_number" => $vaultNumber,
                    "contents" => $encodedContents,
                    "updated_at" => time()
                ]);
            }
        }

        $this->dirty = false;
    }

    private function isViewerOnline(string $viewerUuid) : bool
    {
        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            if ($online->getUniqueId()->toString() === $viewerUuid) {
                return true;
            }
        }

        return false;
    }
}
