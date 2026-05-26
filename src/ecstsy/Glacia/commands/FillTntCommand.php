<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\redstone\RedstoneDispenserManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\block\Block;
use pocketmine\block\TNT;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use function array_values;
use function in_array;
use function strtolower;
use function trim;

final class FillTntCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("type", true));
        $this->registerArgument(1, new IntegerArgument("amount", true));
        $this->registerArgument(2, new IntegerArgument("radius", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        $type = strtolower(trim((string) ($args["type"] ?? "vanilla")));
        $amount = (int) ($args["amount"] ?? 64);
        $radius = (int) ($args["radius"] ?? 0);
        $radius = max(0, min(16, $radius));

        $template = $this->resolveTemplateItem($sender, $type);
        if ($template === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown type. Use: vanilla|gigantic|lethal|lucky|tactical|hand"));
            return;
        }

        $count = max(1, min($amount, $template->getMaxStackSize()));
        $targets = $radius > 0
            ? $this->collectDispensingBlocksAround($sender, $radius)
            : [$sender->getTargetBlock(6)];
        $targets = array_values(array_filter($targets, static fn($b): bool => $b instanceof Block && RedstoneDispenserManager::isDispensingBlock($b)));

        if ($targets === []) {
            $sender->sendMessage(C::colorize($radius > 0
                ? "&r&cNo Glacia dispensers/droppers found in radius."
                : "&r&cLook at a Glacia dispenser or dropper (within range)."));
            return;
        }

        $filled = 0;
        foreach ($targets as $block) {
            if (RedstoneDispenserManager::fillAllSlots($block, $template, $count)) {
                ++$filled;
            }
        }
        if ($filled <= 0) {
            $sender->sendMessage(C::colorize("&r&cCould not fill any target blocks."));
            return;
        }

        $typeLabel = $type === "hand" ? "held TNT" : $type . " TNT";
        $sender->sendMessage(C::colorize("&r&aFilled &f{$filled}&a dispenser/dropper block(s) with &f{$typeLabel}&a (&f{$count}&ax9 slots)."));

        GlaciaUtils::logAdminAction("FillTnt", [
            "player" => $sender->getName(),
            "world" => $sender->getWorld()->getFolderName(),
            "type" => $type,
            "amount" => $count,
            "radius" => $radius,
            "targets" => $filled
        ]);
    }

    private function resolveTemplateItem(Player $sender, string $type): ?Item {
        return match ($type) {
            "", "vanilla", "normal", "tnt" => VanillaBlocks::TNT()->asItem(),
            "gigantic", "giga" => GlaciaItemFactory::create(CustomTNTIds::GIGANTIC),
            "lethal" => GlaciaItemFactory::create(CustomTNTIds::LETHAL),
            "lucky" => GlaciaItemFactory::create(CustomTNTIds::LUCKY),
            "tactical", "tac" => GlaciaItemFactory::create(CustomTNTIds::TACTICAL),
            "hand", "held" => $this->resolveHeldTnt($sender),
            default => null,
        };
    }

    private function resolveHeldTnt(Player $sender): ?Item {
        $item = $sender->getInventory()->getItemInHand();
        if ($item instanceof ItemBlock && $item->getBlock() instanceof TNT) {
            return $item;
        }

        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        $id = $glacia?->getString("glaciaItem", "") ?? "";
        if ($id === "") {
            $id = $item->getNamedTag()->getString("glaciaItem", "");
        }
        if (!in_array($id, [CustomTNTIds::GIGANTIC, CustomTNTIds::LETHAL, CustomTNTIds::LUCKY, CustomTNTIds::TACTICAL], true)) {
            return null;
        }
        return $item;
    }

    /**
     * @return list<Block>
     */
    private function collectDispensingBlocksAround(Player $player, int $radius): array {
        $world = $player->getWorld();
        $p = $player->getPosition();
        $cx = (int) $p->x;
        $cy = (int) $p->y;
        $cz = (int) $p->z;
        $out = [];

        for ($x = $cx - $radius; $x <= $cx + $radius; ++$x) {
            for ($y = $cy - $radius; $y <= $cy + $radius; ++$y) {
                for ($z = $cz - $radius; $z <= $cz + $radius; ++$z) {
                    if (!$world->isInWorld($x, $y, $z)) {
                        continue;
                    }
                    $block = $world->getBlockAt($x, $y, $z);
                    if (RedstoneDispenserManager::isDispensingBlock($block)) {
                        $out[] = $block;
                    }
                }
            }
        }
        return $out;
    }

    public function getPermission(): string {
        return "glacia.filltnt";
    }
}
