<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\claims;

use diamondgold\DummyItemsBlocks\block\enum\StructureBlockType;
use diamondgold\DummyItemsBlocks\block\StructureBlock;
use diamondgold\DummyItemsBlocks\tile\TileNames;
use diamondgold\DummyItemsBlocks\tile\TileNbtTagNames;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\block\tile\Tile;
use pocketmine\item\ItemBlock;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\LongTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\World;
use function floor;
use function max;

final class ChunkBorderManager
{
    /** @var array<string, TaskHandler> */
    private static array $active = [];
    /** @var array<string, Vector3> */
    private static array $renderedAnchors = [];
    /** @var array<string, string> */
    private static array $lastRenderState = [];

    private static int $refreshTicks = 8;
    private static int $baseYOffset = -1;
    private static bool $fullHeight = true;
    private static int $height = 6;

    public static function toggle(Player $player): bool
    {
        self::loadFromConfig();

        $key = $player->getUniqueId()->toString();
        if (isset(self::$active[$key])) {
            self::disable($player, $key);
            return false;
        }

        self::$active[$key] = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function () use ($player, $key): void {
                if (!$player->isConnected()) {
                    self::disable($player, $key);
                    return;
                }

                self::renderBorders($player);
            }),
            self::$refreshTicks
        );

        self::renderBorders($player);
        return true;
    }

    private static function loadFromConfig(): void
    {
        $misc = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $block = (array) ($misc?->get("chunk-borders", []) ?? []);

        self::$refreshTicks = max(4, (int) ($block["refresh-ticks"] ?? 8));
        self::$baseYOffset = (int) ($block["base-y-offset"] ?? -1);
        self::$fullHeight = (bool) ($block["full-height"] ?? true);
        self::$height = max(3, (int) ($block["height"] ?? 6));
    }

    private static function disable(Player $player, string $key): void
    {
        if (isset(self::$active[$key])) {
            self::$active[$key]->cancel();
            unset(self::$active[$key]);
        }

        $anchor = self::$renderedAnchors[$key] ?? null;
        // Disconnected players have no session — restoring fake blocks would throw LogicException.
        if ($anchor !== null && $player->isConnected()) {
            self::restoreAnchor($player, $anchor);
        }

        unset(self::$renderedAnchors[$key], self::$lastRenderState[$key]);
    }

    private static function renderBorders(Player $player): void
    {
        $world = $player->getWorld();
        $pos = $player->getPosition();

        $chunkMinX = ((int) floor($pos->x) >> 4) << 4;
        $chunkMinZ = ((int) floor($pos->z) >> 4) << 4;
        $minY = self::$fullHeight ? $world->getMinY() : max($world->getMinY(), (int) floor($pos->y) + self::$baseYOffset);
        $height = self::$fullHeight ? ($world->getMaxY() - $world->getMinY()) : self::$height;
        $anchor = new Vector3($chunkMinX, $minY, $chunkMinZ);

        $stateKey = $world->getFolderName() . ":" . $chunkMinX . ":" . $minY . ":" . $chunkMinZ . ":" . $height;
        $playerKey = $player->getUniqueId()->toString();

        if ((self::$lastRenderState[$playerKey] ?? null) === $stateKey) {
            return;
        }

        $oldAnchor = self::$renderedAnchors[$playerKey] ?? null;
        if ($oldAnchor !== null) {
            self::restoreAnchor($player, $oldAnchor);
        }

        self::sendStructureOutline($player, $world, $anchor, $height);
        self::$renderedAnchors[$playerKey] = $anchor;
        self::$lastRenderState[$playerKey] = $stateKey;
    }

    private static function sendStructureOutline(Player $player, World $world, Vector3 $anchor, int $height): void
    {
        $block = self::resolveStructureBlock();
        if ($block === null) {
            return;
        }

        $network = $player->getNetworkSession();
        $network->sendDataPacket(
            UpdateBlockPacket::create(
                BlockPosition::fromVector3($anchor),
                TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($block->getStateId()),
                UpdateBlockPacket::FLAG_NETWORK,
                UpdateBlockPacket::DATA_LAYER_NORMAL
            )
        );

        $tile = CompoundTag::create()
            ->setString(Tile::TAG_ID, TileNames::STRUCTURE_BLOCK)
            ->setInt(Tile::TAG_X, (int) $anchor->x)
            ->setInt(Tile::TAG_Y, (int) $anchor->y)
            ->setInt(Tile::TAG_Z, (int) $anchor->z)
            ->setTag(TileNbtTagNames::animationMode, new ByteTag(0))
            ->setTag(TileNbtTagNames::animationSeconds, new FloatTag(0))
            ->setTag(TileNbtTagNames::data, new IntTag(1))
            ->setTag(TileNbtTagNames::dataField, new StringTag(""))
            ->setTag(TileNbtTagNames::ignoreEntities, new ByteTag(1))
            ->setTag(TileNbtTagNames::includePlayers, new ByteTag(0))
            ->setTag(TileNbtTagNames::integrity, new FloatTag(100))
            ->setTag(TileNbtTagNames::isMovable, new ByteTag(1))
            ->setTag(TileNbtTagNames::isPowered, new ByteTag(0))
            ->setTag(TileNbtTagNames::mirror, new ByteTag(0))
            ->setTag(TileNbtTagNames::redstoneSaveMode, new IntTag(0))
            ->setTag(TileNbtTagNames::removeBlocks, new ByteTag(0))
            ->setTag(TileNbtTagNames::rotation, new ByteTag(0))
            ->setTag(TileNbtTagNames::seed, new LongTag(0))
            ->setTag(TileNbtTagNames::showBoundingBox, new ByteTag(1))
            ->setTag(TileNbtTagNames::structureName, new StringTag("glacia_chunk_border"))
            ->setTag(TileNbtTagNames::xStructureOffset, new IntTag(0))
            ->setTag(TileNbtTagNames::yStructureOffset, new IntTag(0))
            ->setTag(TileNbtTagNames::zStructureOffset, new IntTag(0))
            ->setTag(TileNbtTagNames::xStructureSize, new IntTag(16))
            ->setTag(TileNbtTagNames::yStructureSize, new IntTag(max(1, $height)))
            ->setTag(TileNbtTagNames::zStructureSize, new IntTag(16));

        $network->sendDataPacket(
            BlockActorDataPacket::create(
                BlockPosition::fromVector3($anchor),
                new CacheableNbt($tile)
            )
        );
    }

    private static function restoreAnchor(Player $player, Vector3 $anchor): void
    {
        if (!$player->isConnected()) {
            return;
        }

        foreach ($player->getWorld()->createBlockUpdatePackets([$anchor]) as $packet) {
            $player->getNetworkSession()->sendDataPacket($packet);
        }
    }

    private static function resolveStructureBlock(): ?StructureBlock
    {
        $item = StringToItemParser::getInstance()->parse("structure_block");
        if (!$item instanceof ItemBlock) {
            return null;
        }

        $block = $item->getBlock();
        if (!$block instanceof StructureBlock) {
            return null;
        }

        return (clone $block)->setType(StructureBlockType::SAVE);
    }
}
