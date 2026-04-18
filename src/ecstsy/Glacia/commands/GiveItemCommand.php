<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\items\ListGlaciaItemsSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class GiveItemCommand extends BaseCommand {

    protected function prepare(): void{
        $this->setPermission("glacia.give-item");
        $this->registerArgument(0, new RawStringArgument("item"));
        $this->registerArgument(1, new IntegerArgument("amount", true));
        $this->registerArgument(2, new RawStringArgument("extra1", true));
        $this->registerArgument(3, new RawStringArgument("extra2", true));
        $this->registerArgument(4, new RawStringArgument("extra3", true));

        $this->registerSubCommand(new ListGlaciaItemsSubCommand(Loader::getInstance(), "list", "List all available Glacia items"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void{
        if(!$sender instanceof Player){
            $sender->sendMessage(C::RED . "This command can only be used in-game.");
            return;
        }

        $itemName = strtolower($args['item']);
        $amount = isset($args['amount']) ? (int) $args['amount'] : 1;

        $item = null;
        $items = [];

        try {
            switch($itemName) {
                case 'enchantment_book':
                    $enchantName = $args['extra1'] ?? null;
                    $level = isset($args['extra2']) ? (int)$args['extra2'] : 1;
                    if ($enchantName === null) {
                        $sender->sendMessage(C::RED . "Usage: /giveitem enchantment_book <amount> <enchantment> [level]");
                        return;
                    }

                    $enchantment = CustomEnchantments::getEnchantmentByName($enchantName);
                    if ($enchantment === null) {
                        $sender->sendMessage(C::RED . "Invalid enchantment name: " . $enchantName);
                        return;
                    }

                    $item = GlaciaItemFactory::enchantmentBook($enchantment, $level);
                    break;

                case 'orb':
                    $type = $args['extra1'] ?? null;
                    $max = isset($args['extra2']) ? (int)$args['extra2'] : 0;
                    $success = isset($args['extra3']) ? (int)$args['extra3'] : 100;

                    if ($type === null || $max <= 0) {
                        $sender->sendMessage(C::RED . "Usage: /giveitem orb <amount> <type> <max> [success]");
                        return;
                    }

                    $item = GlaciaItemFactory::enchantOrb($type, $max, $amount, $success);
                    break;

                case 'random_book':
                case 'random_magicdust':
                case 'primal_dust':
                case 'magicdust':
                    $group = $args['extra1'] ?? null;
                    if ($group === null) {
                        $sender->sendMessage(C::RED . "Usage: /giveitem {$itemName} <amount> <group> [percentage]");
                        return;
                    }

                    $percent = isset($args['extra2']) ? (int)$args['extra2'] : 100;

                    $item = match ($itemName) {
                        'random_book' => GlaciaItemFactory::randomEnchantBook($group),
                        'random_magicdust' => GlaciaItemFactory::randomMagicDust($group),
                        'primal_dust' => GlaciaItemFactory::primalDust($group, $percent),
                        'magicdust' => GlaciaItemFactory::magicDust($group, $percent),
                    };
                    break;
                case 'faction_xp_booster':
                    $multiplierInput = $args['extra1'] ?? null;
                    $durationInput = $args['extra2'] ?? null;

                    if ($multiplierInput === null || $durationInput === null) {
                        $sender->sendMessage(C::RED . "Usage: /giveitem faction_xp_booster <amount> <multiplier> <duration>");
                        return;
                    }

                    $multiplier = $this->parseMultiplier($multiplierInput);
                    $durationSeconds = GeneralUtils::parseTimeString($durationInput);
                    if ($multiplier <= 0 || $durationSeconds <= 0) {
                        $sender->sendMessage(C::RED . "Multiplier and duration must both be greater than 0.");
                        return;
                    }

                    $item = GlaciaItemFactory::factionXpBooster($multiplier, $durationInput);
                    break;

                    case 'customarmor':
                        $set = $args['extra1'] ?? null;
                        $piece = $args['extra2'] ?? 'full';
                        $enchanted = strtolower((string)($args['extra3'] ?? 'false'));
                        $enchanted = in_array($enchanted, ['1', 'true', 'yes', 'enchanted'], true);

                        if ($set === null) {
                            $sender->sendMessage(C::RED . "Usage: /giveitem customarmor <amount> <set> [helmet|chestplate|leggings|boots|full] [enchanted]");
                            return;
                        }

                        $result = GlaciaItemFactory::customArmor($set, $piece, $enchanted);

                        if (is_array($result)) {
                            foreach ($result as $armorItem) {
                                $armorItem->setCount($amount);
                                $items[] = $armorItem;
                            }
                        } else {
                            $item = $result;
                        }
                        break;
                    case 'customweapon':
                        $set = $args['extra1'] ?? null;
                        $type = $args['extra2'] ?? 'sword';

                        if ($set === null) {
                            $sender->sendMessage(C::RED . "Usage: /giveitem customweapon <amount> <set> [sword|bow]");
                            return;
                        }

                        $item = GlaciaItemFactory::customWeapon($set, $type);
                        break;
                default:
                    $item = GlaciaItemFactory::create($itemName);
                    break;
            }
        } catch (\InvalidArgumentException $e) {
            $sender->sendMessage(C::RED . "Error: " . $e->getMessage());
            return;
        }

        if (!empty($items)) {
            $sender->getInventory()->addItem(...$items);
            $sender->sendMessage(C::GREEN . "You have received the full armor set.");
            return;
        }

        if ($item !== null) {
            $item->setCount($amount);
            $sender->getInventory()->addItem($item);
            $sender->sendMessage(C::GREEN . "You have received " . $item->getName());
        } else {
            $sender->sendMessage(C::RED . "Could not create item: " . $itemName);
        }
    }

    private function parseMultiplier(string $input): float
    {
        $normalized = strtolower(trim($input));
        $normalized = str_replace("x", "", $normalized);

        return (float) $normalized;
    }
}
