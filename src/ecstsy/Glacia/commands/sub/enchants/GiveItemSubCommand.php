<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\enchants;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GiveItemSubCommand extends BaseSubCommand {

    private array $availableItems = [
        'whitescroll', 'holywhitescroll', 'blackscroll', 'renametag', 'transmog',
        'heroicupgrade',
        'charmshard', 'charmdust',
        'mystery', 'magic', 'randomizer', 'secret',
        'blocktrak', 'mobtrak', 'killtrak', 'xptrak',
        'slotticket', 'heroicslotticket',
        'orb',
    ];

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->setPermissionMessage(C::colorize("&r&cYou don't have permission to do that!"));
        $this->registerArgument(0, new RawStringArgument("name", false));
        $this->registerArgument(1, new RawStringArgument("item", false));
        $this->registerArgument(2, new IntegerArgument("amount", false));

        $this->registerArgument(3, new RawStringArgument("extra1", true));
        $this->registerArgument(4, new IntegerArgument("extra2", true));
        $this->registerArgument(5, new IntegerArgument("extra3", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!isset($args["name"], $args["item"], $args["amount"])) {
            $sender->sendMessage(C::colorize(str_replace("{usage}", $this->getUsage(), "&cUsage: {usage}")));
            return;
        }

        $player = PlayerUtils::getPlayerByPrefix($args["name"]);
        if ($player === null || !$player->isOnline()) {
            $sender->sendMessage(C::colorize("&r&cThat player is not online!"));
            return;
        }

        $amount = max(1, (int) $args["amount"]);
        $itemKey = strtolower((string) $args["item"]);

        if (!in_array($itemKey, $this->availableItems, true)) {
            $sender->sendMessage($this->getUsage());
            return;
        }

        $item = $this->buildItem($sender, $aliasUsed, $itemKey, $amount, $args);
        if ($item === null) {
            return;
        }

        $item->setCount($amount);

        $inventory = $player->getInventory();
        if ($inventory->canAddItem($item)) {
            $inventory->addItem($item);
        } else {
            $player->getWorld()->dropItem($player->getLocation()->asVector3(), $item);
        }

        $displayName = $item->getCustomName() !== "" ? $item->getCustomName() : $item->getName();
        $sender->sendMessage(C::colorize(str_replace(
            ["{player}", "{item}", "{amount}"],
            [$player->getName(), $displayName, (string) $amount],
            "&7Added {amount}x {item}&r&7 for player &b{player}"
        )));

        if ($sender instanceof Player) {
            PlayerUtils::playSound($sender, "random.orb");
        }
    }

    private function buildItem(CommandSender $sender, string $aliasUsed, string $itemKey, int $amount, array $args): ?Item
    {
        switch ($itemKey) {
            case 'orb':
                if (!isset($args["extra1"], $args["extra2"], $args["extra3"])) {
                    $sender->sendMessage(C::colorize("&cUsage: /$aliasUsed <player> orb <amount> <weapon|armor|tool> <max> <success>"));
                    return null;
                }
                $orbType = strtolower((string) $args["extra1"]);
                if (!in_array($orbType, ["weapon", "armor", "tool"], true)) {
                    $sender->sendMessage(C::colorize("&cInvalid orb type. Use: weapon, armor, or tool."));
                    return null;
                }
                return GlaciaItemFactory::enchantOrb($orbType, (int) $args["extra2"], $amount, (int) $args["extra3"]);

            case 'blackscroll':
                $success = isset($args["extra1"]) ? (int) $args["extra1"] : null;
                return GlaciaItemFactory::blackscroll($success);

            case 'magic':
                if (!isset($args["extra1"])) {
                    $sender->sendMessage(C::colorize("&cUsage: /$aliasUsed <player> magic <amount> <group> [percent]"));
                    return null;
                }
                $percent = isset($args["extra2"]) ? (int) $args["extra2"] : 100;
                return GlaciaItemFactory::magicDust(strtolower((string) $args["extra1"]), $percent);

            case 'randomizer':
                if (!isset($args["extra1"])) {
                    $sender->sendMessage(C::colorize("&cUsage: /$aliasUsed <player> randomizer <amount> <group>"));
                    return null;
                }
                return GlaciaItemFactory::randomMagicDust(strtolower((string) $args["extra1"]));

            case 'secret':
                if (!isset($args["extra1"])) {
                    $sender->sendMessage(C::colorize("&cUsage: /$aliasUsed <player> secret <amount> <group>"));
                    return null;
                }
                return GlaciaItemFactory::randomEnchantBook(strtolower((string) $args["extra1"]));

            case 'mystery':
                return GlaciaItemFactory::mysteryDust();

            case 'whitescroll':
                return GlaciaItemFactory::whitescroll();

            case 'holywhitescroll':
                return GlaciaItemFactory::create("holy_whitescroll");

            case 'renametag':
                return GlaciaItemFactory::renametag();

            case 'transmog':
                return GlaciaItemFactory::create("transmog");

            case 'heroicupgrade':
                return GlaciaItemFactory::heroicUpgrade(null);
            case 'charmshard':
                return GlaciaItemFactory::charmShard(null);
            case 'charmdust':
                return GlaciaItemFactory::charmDust(null);

            case 'blocktrak':
                return GlaciaItemFactory::create("block_tracker");

            case 'mobtrak':
                return GlaciaItemFactory::create("mob_tracker");

            case 'killtrak':
                return GlaciaItemFactory::create("kill_tracker");

            case 'xptrak':
                return GlaciaItemFactory::create("xp_tracker");

            case 'slotticket':
                return GlaciaItemFactory::slotTicket();

            case 'heroicslotticket':
                return GlaciaItemFactory::heroicSlotTicket();
        }

        $sender->sendMessage(C::colorize("&cUnknown item: &f{$itemKey}"));
        return null;
    }

    public function getUsage(): string
    {
        $messages = [
            "/ge giveitem <player> <item> <count> [extra args]",
            "&r&fAvailable: &7" . implode(", ", $this->availableItems),
        ];

        return C::colorize(implode("\n", $messages));
    }

    public function getPermission(): string
    {
        return "glacia.give-item";
    }
}
