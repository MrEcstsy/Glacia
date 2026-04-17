<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Zombie;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\utils\TextFormat as C;

final class CombatLoggerEntity extends Zombie {

    public const NETWORK_ID = EntityIds::ZOMBIE;

    public string $baseTag = "";
    protected string $ownerName = "";
    /** @var Item[] snapshot of the player's inventory (indexed by slot) */
    protected array $storedInventory = [];
    /** @var Item[] snapshot of armor */
    protected array $storedArmor = [];
    protected int $storedXp = 0;
    protected string $ownerUuid = "";

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
        $this->setNoClientPredictions(true);

        $this->setMaxHealth(50);
        $this->setHealth(50);

        GlaciaUtils::updateEntityTag($this);

        $this->ownerName = $nbt->getString("ownerName", "");
        $this->ownerUuid = $nbt->getString("ownerUuid", "");
        $this->baseTag = $nbt->getString("baseTag", "");
        $this->storedXp = $nbt->getInt("storedXp", 0);

        $this->storedInventory = [];
        $invTag = $nbt->getCompoundTag("storedInventory");
        if ($invTag !== null) {
            foreach ($invTag->getValue() as $slot => $tag) {
                $item = ItemUtils::decodeItem($tag->getValue());
                if ($item instanceof Item && !$item->isNull()) {
                    $this->storedInventory[(int) $slot] = $item;
                }
            }
        }

        $this->storedArmor = [];
        $armorTag = $nbt->getCompoundTag("storedArmor");
        if ($armorTag !== null) {
            foreach ($armorTag->getValue() as $slot => $tag) {
                $item = ItemUtils::decodeItem($tag->getValue());
                if ($item instanceof Item && !$item->isNull()) {
                    $this->storedArmor[(int) $slot] = $item;
                }
            }
        }

        if ($this->baseTag !== "") {
            $this->setNameTag($this->baseTag);
        }

        $this->equipFromStored();
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.95, 0.6, 1.62);
    }

    public function onUpdate(int $currentTick): bool {
        $this->setMotion(Vector3::zero());

        if ($currentTick % 10 === 0) {
            GlaciaUtils::updateEntityTag($this);
        }

        return parent::onUpdate($currentTick);
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();

        $nbt->setString("ownerName", $this->ownerName);
        $nbt->setString("ownerUuid", $this->ownerUuid);
        $nbt->setString("baseTag", $this->baseTag);
        $nbt->setInt("storedXp", $this->storedXp);

        $invTag = new CompoundTag();
        foreach ($this->storedInventory as $slot => $item) {
            if ($item instanceof Item && !$item->isNull()) {
                $invTag->setString((string) $slot, ItemUtils::encodeItem($item));
            }
        }
        $nbt->setTag("storedInventory", $invTag);

        $armorTag = new CompoundTag();
        foreach ($this->storedArmor as $slot => $item) {
            if ($item instanceof Item && !$item->isNull()) {
                $armorTag->setString((string) $slot, ItemUtils::encodeItem($item));
            }
        }
        $nbt->setTag("storedArmor", $armorTag);

        return $nbt;
    }

    public static function getNetworkTypeId(): string {
        return self::NETWORK_ID;
    }

    /**
     * Populate this entity from a Player snapshot.
     * Call this before you clear the player's inventory / disconnect them.
     */
    public function populateFromPlayer(Player $player): void {
        $this->ownerName = strtolower($player->getName());
        $this->baseTag = $player->getNameTag();
        $this->setNameTag($this->baseTag);        

        $this->storedInventory = [];
        foreach ($player->getInventory()->getContents() as $slot => $item) {
            $this->storedInventory[$slot] = clone $item;
        }

        $this->storedArmor = [];
        foreach ($player->getArmorInventory()->getContents() as $slot => $item) {
            $this->storedArmor[$slot] = clone $item;
        }

        $this->setMaxHealth(50);
        $this->setHealth(50);

        $this->equipFromStored();

        $this->setNoClientPredictions(true);

        GlaciaUtils::updateEntityTag($this);
        $this->ownerUuid = $player->getUniqueId()->toString();
    }

    public function getOwnerUUID(): string {
        return $this->ownerUuid;
    }

    public function getOwnerName(): string {
        return $this->ownerName;
    }

    /**
     * @return Item[]
     */
    public function getStoredInventory(): array {
        return $this->storedInventory;
    }

    /**
     * @return Item[]
     */
    public function getStoredArmor(): array {
        return $this->storedArmor;
    }


    protected function equipFromStored(): void {
        try {
            if (!empty($this->storedArmor)) {
                $armorInv = $this->getArmorInventory();
                foreach ($this->storedArmor as $slot => $item) {
                    $armorInv->setItem($slot, clone $item);
                }
            }
        } catch (\Throwable $e) {

        }
    }


    public function getXpDropAmount(): int {
        return 0;
    }

    public function setStoredXp(int $xp): void {
        $this->storedXp = $xp;
    }

    public function getStoredXp(): int {
        return $this->storedXp;
    }
    
    public function getDrops(): array {
        return [];
    }


}
