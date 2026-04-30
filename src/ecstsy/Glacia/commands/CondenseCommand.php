<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class CondenseCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        $inventory = $sender->getInventory();
        $converted = 0;

        foreach ($this->getRecipes() as [$input, $output, $ratio]) {
            $count = $this->countPlainItems($inventory->getContents(), $input);
            if ($count < $ratio) {
                continue;
            }

            $blocks = intdiv($count, $ratio);
            $remainder = $count % $ratio;

            $this->removePlainItems($sender, $input);
            if ($remainder > 0) {
                $leftoverInput = clone $input;
                $leftoverInput->setCount($remainder);
                $inventory->addItem($leftoverInput);
            }

            $out = clone $output;
            $out->setCount($blocks);
            $leftovers = $inventory->addItem($out);
            foreach ($leftovers as $leftover) {
                $sender->getWorld()->dropItem($sender->getPosition(), $leftover);
            }

            $converted += $blocks;
        }

        if ($converted <= 0) {
            $sender->sendMessage(C::colorize("&r&eNothing to condense."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&aCondensed items into &f{$converted} &ablocks."));
    }

    /**
     * @return array<int, array{0: Item, 1: Item, 2: int}>
     */
    private function getRecipes(): array
    {
        $map = [
            ["iron_ingot", "iron_block", 9],
            ["gold_ingot", "gold_block", 9],
            ["diamond", "diamond_block", 9],
            ["emerald", "emerald_block", 9],
            ["redstone", "redstone_block", 9],
            ["coal", "coal_block", 9],
            ["lapis_lazuli", "lapis_block", 9],
            ["wheat", "hay_bale", 9],
        ];

        $recipes = [];
        foreach ($map as [$inputId, $outputId, $ratio]) {
            $input = StringToItemParser::getInstance()->parse($inputId);
            $output = StringToItemParser::getInstance()->parse($outputId);
            if ($input instanceof Item && $output instanceof Item) {
                $recipes[] = [$input, $output, $ratio];
            }
        }

        return $recipes;
    }

    /**
     * @param array<int, Item> $contents
     */
    private function countPlainItems(array $contents, Item $input): int
    {
        $count = 0;
        foreach ($contents as $item) {
            if ($item->getTypeId() !== $input->getTypeId() || !$this->isPlain($item)) {
                continue;
            }
            $count += $item->getCount();
        }

        return $count;
    }

    private function removePlainItems(Player $player, Item $input): void
    {
        $inventory = $player->getInventory();
        foreach ($inventory->getContents() as $slot => $item) {
            if ($item->getTypeId() !== $input->getTypeId() || !$this->isPlain($item)) {
                continue;
            }
            $inventory->setItem($slot, VanillaItems::AIR());
        }
    }

    private function isPlain(Item $item): bool
    {
        return !$item->hasEnchantments()
            && $item->getCustomName() === ""
            && $item->getLore() === []
            && !$item->hasNamedTag();
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
