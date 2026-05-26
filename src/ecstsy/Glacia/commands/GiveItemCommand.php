<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\items\ListGlaciaItemsSubCommand;
use ecstsy\Glacia\commands\sub\items\GiveItemMenuSubCommand;
use ecstsy\Glacia\commands\sub\items\SearchGlaciaItemsSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\etherealcrates\EtherealCrateRepository;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\Glacia\utils\GlaciaUtils;
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
        $this->registerSubCommand(new SearchGlaciaItemsSubCommand(Loader::getInstance(), "search", "Search the Glacia item catalogue"));
        $this->registerSubCommand(new GiveItemMenuSubCommand(Loader::getInstance(), "menu", "Open the give-item menu"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void{
        if(!$sender instanceof Player){
            $sender->sendMessage(C::colorize("&cThis command can only be used in-game."));
            return;
        }

        $itemName = strtolower($args['item']);
        $amount = isset($args['amount']) ? (int) $args['amount'] : 1;

        $item = null;
        $items = [];

        try {
            switch($itemName) {
                case 'bank_note':
                case 'money_note':
                case 'moneynote':
                case 'econote':
                    $noteAmount = isset($args['extra1']) ? (int) GeneralUtils::parseRandomNumber((string) $args['extra1']) : 0;
                    if ($noteAmount <= 0) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem {$itemName} <amount> <value> [signer|default]"));
                        return;
                    }

                    $signer = $this->resolveSigner($sender, $args['extra2'] ?? null);
                    $item = GlaciaItemFactory::bankNoteSigned($signer, $noteAmount);
                    break;

                case 'xpnote':
                case 'xp_note':
                    $noteAmount = isset($args['extra1']) ? (int) GeneralUtils::parseRandomNumber((string) $args['extra1']) : 0;
                    if ($noteAmount <= 0) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem {$itemName} <amount> <value> [signer|default]"));
                        return;
                    }

                    $signer = $this->resolveSigner($sender, $args['extra2'] ?? null);
                    $item = GlaciaItemFactory::xpNoteSigned($signer, $noteAmount);
                    break;

                case 'admin_coin_note':
                case 'admincoins':
                case 'admin_coin':
                    $noteAmount = isset($args['extra1']) ? (int) GeneralUtils::parseRandomNumber((string) $args['extra1']) : 0;
                    if ($noteAmount <= 0) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem {$itemName} <amount> <value> [signer|default]"));
                        return;
                    }

                    $signer = $this->resolveSigner($sender, $args['extra2'] ?? null);
                    $item = GlaciaItemFactory::adminCoinNoteSigned($signer, $noteAmount);
                    break;

                case 'enchantment_book':
                    $enchantName = $args['extra1'] ?? null;
                    $level = isset($args['extra2']) ? (int)$args['extra2'] : 1;
                    if ($enchantName === null) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem enchantment_book <amount> <enchantment> [level]"));
                        return;
                    }

                    $enchantment = CustomEnchantments::getEnchantmentByName($enchantName);
                    if ($enchantment !== null) {
                        $item = GlaciaItemFactory::enchantmentBook($enchantment, $level);
                        break;
                    }

                    $item = GlaciaItemFactory::vanillaEnchantmentBook((string) $enchantName, $level);
                    if ($item === null) {
                        $sender->sendMessage(C::colorize("&cInvalid enchantment name: " . $enchantName));
                        return;
                    }
                    break;

                case 'orb':
                    $type = $args['extra1'] ?? null;
                    $max = isset($args['extra2']) ? (int)$args['extra2'] : 0;
                    $success = isset($args['extra3']) ? (int)$args['extra3'] : 100;

                    if ($type === null || $max <= 0) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem orb <amount> <type> <max> [success]"));
                        return;
                    }

                    $item = GlaciaItemFactory::enchantOrb($type, $max, $amount, $success);
                    break;
                case 'armor_orb_script':
                case 'armororbscript':
                    $item = GlaciaItemFactory::create('armor_orb_script');
                    break;
                case 'weapon_orb_script':
                case 'weaponorbscript':
                    $item = GlaciaItemFactory::create('weapon_orb_script');
                    break;

                case 'random_book':
                case 'random_magicdust':
                case 'primal_dust':
                case 'magicdust':
                    $group = $args['extra1'] ?? null;
                    if ($group === null) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem {$itemName} <amount> <group> [percentage]"));
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
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem faction_xp_booster <amount> <multiplier> <duration>"));
                        return;
                    }

                    $multiplier = $this->parseMultiplier($multiplierInput);
                    $durationSeconds = GeneralUtils::parseTimeString($durationInput);
                    if ($multiplier <= 0 || $durationSeconds <= 0) {
                        $sender->sendMessage(C::colorize("&cMultiplier and duration must both be greater than 0."));
                        return;
                    }

                    $item = GlaciaItemFactory::factionXpBooster($multiplier, $durationInput);
                    break;
                case 'zone_xp_booster':
                case 'zonexpbooster':
                case 'zone_booster_xp':
                    $multiplierInput = $args['extra1'] ?? null;
                    $durationInput = $args['extra2'] ?? null;
                    if ($multiplierInput === null || $durationInput === null) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem zone_xp_booster <amount> <multiplier> <duration>"));
                        return;
                    }

                    $multiplier = $this->parseMultiplier((string) $multiplierInput);
                    $durationSeconds = GeneralUtils::parseTimeString((string) $durationInput);
                    if ($multiplier <= 0 || $durationSeconds <= 0) {
                        $sender->sendMessage(C::colorize("&cMultiplier and duration must both be greater than 0."));
                        return;
                    }

                    $item = GlaciaItemFactory::zoneXpBooster("", $multiplier, (string) $durationInput);
                    break;
                case 'zone_fishing_booster':
                case 'zonefishingbooster':
                case 'zone_booster_fishing':
                    $zoneInput = $args['extra1'] ?? null;
                    $multiplierInput = $args['extra2'] ?? null;
                    $durationInput = $args['extra3'] ?? null;
                    if ($zoneInput === null || $multiplierInput === null || $durationInput === null) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem zone_fishing_booster <amount> <zone> <multiplier> <duration>"));
                        return;
                    }

                    $multiplier = $this->parseMultiplier((string) $multiplierInput);
                    $durationSeconds = GeneralUtils::parseTimeString((string) $durationInput);
                    if ($multiplier <= 0 || $durationSeconds <= 0) {
                        $sender->sendMessage(C::colorize("&cMultiplier and duration must both be greater than 0."));
                        return;
                    }

                    $item = GlaciaItemFactory::zoneFishingBooster(strtolower(trim((string) $zoneInput)), $multiplier, (string) $durationInput);
                    break;
                case 'disguise_unlock':
                case 'disguise':
                    $disguiseId = $args['extra1'] ?? null;
                    if ($disguiseId === null) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem {$itemName} <amount> <disguise>"));
                        return;
                    }

                    $item = GlaciaItemFactory::disguiseUnlock((string) $disguiseId);
                    break;
                case 'lootbox':
                case 'loot_box':
                case 'lb':
                    $boxLookup = $args['extra1'] ?? null;
                    if ($boxLookup === null) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem {$itemName} <amount> <box>"));
                        return;
                    }

                    $item = GlaciaItemFactory::lootbox((string) $boxLookup, $sender);
                    break;
                case 'ethereal_crate':
                case 'etherealcrate':
                case 'ecrate':
                    $crateLookup = $args['extra1'] ?? null;
                    if ($crateLookup === null) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem {$itemName} <amount> <crate>"));
                        return;
                    }

                    $item = GlaciaItemFactory::etherealCrate((string) $crateLookup, $sender);
                    break;
                case 'ethereal_crate_icon':
                case 'etherealcrateicon':
                case 'ecrateicon':
                    $item = GlaciaItemFactory::etherealCrateIcon();
                    break;
                case 'collection_chest':
                case 'collectionchest':
                case 'cchest':
                case 'ccollect':
                    $item = GlaciaItemFactory::collectionChest();
                    break;
                case 'harvester_chest':
                case 'harvesterchest':
                case 'hchest':
                case 'harvester':
                    $item = GlaciaItemFactory::harvesterChest();
                    break;
                case 'watchman_supplement':
                case 'watchdogsupplement':
                case 'watchdog_supplement':
                case 'supplement':
                    $days = isset($args['extra1']) ? max(1, (int) GeneralUtils::parseRandomNumber((string) $args['extra1'])) : 1;
                    $item = GlaciaItemFactory::watchdogSupplement($days);
                    break;
                case 'soul_tracker':
                case 'soultracker':
                case 'soul_trak':
                case 'soultrak':
                    $item = GlaciaItemFactory::create('soul_tracker');
                    break;
                case 'soul_conduit':
                case 'soulconduit':
                case 'legacy_soul_gem':
                    $souls = isset($args['extra1']) ? max(1, (int) GeneralUtils::parseRandomNumber((string) $args['extra1'])) : 1;
                    $item = GlaciaItemFactory::soulConduit($souls);
                    break;
                case 'efficiency_conduit':
                case 'soul_conduit_efficiency':
                case 'soul_efficiency':
                    $souls = isset($args['extra1']) ? max(1, (int) GeneralUtils::parseRandomNumber((string) $args['extra1'])) : 1;
                    $item = GlaciaItemFactory::soulConduitEfficiency($souls);
                    break;
                case 'overlord_conduit':
                case 'soul_conduit_overload':
                case 'soul_conduit_overlord':
                case 'overload_conduit':
                case 'soul_overload':
                    $souls = isset($args['extra1']) ? max(1, (int) GeneralUtils::parseRandomNumber((string) $args['extra1'])) : 1;
                    $item = GlaciaItemFactory::soulConduitOverload($souls);
                    break;
                case 'soul_gem':
                case 'soulgem':
                case 'soul_fuel_gem':
                case 'stable_gem':
                    $souls = isset($args['extra1']) ? max(1, (int) GeneralUtils::parseRandomNumber((string) $args['extra1'])) : 1;
                    $item = GlaciaItemFactory::soulGem($souls);
                    break;
                case 'efficiency_gem':
                case 'efficiency_conduit_gem':
                case 'soul_efficiency_gem':
                    $souls = isset($args['extra1']) ? max(1, (int) GeneralUtils::parseRandomNumber((string) $args['extra1'])) : 1;
                    $item = GlaciaItemFactory::efficiencyConduitGem($souls);
                    break;
                case 'overlord_gem':
                case 'overlord_conduit_gem':
                case 'overload_gem':
                case 'soul_overload_gem':
                    $souls = isset($args['extra1']) ? max(1, (int) GeneralUtils::parseRandomNumber((string) $args['extra1'])) : 1;
                    $item = GlaciaItemFactory::overlordConduitGem($souls);
                    break;
                case 'wraith_pearl':
                case 'wraithpearl':
                    $uses = isset($args['extra1']) ? max(1, (int) GeneralUtils::parseRandomNumber((string) $args['extra1'])) : null;
                    $item = GlaciaItemFactory::wraithPearl($uses);
                    break;
                case 'angel_potion':
                case 'angelpotion':
                    $uses = isset($args['extra1']) ? max(1, (int) GeneralUtils::parseRandomNumber((string) $args['extra1'])) : null;
                    $item = GlaciaItemFactory::angelPotion($uses);
                    break;
                case 'dormant_sigil':
                    $item = GlaciaItemFactory::questSigil('dormant');
                    break;
                case 'awakened_sigil':
                    $item = GlaciaItemFactory::questSigil('awakened');
                    break;
                case 'ascendent_sigil':
                    $item = GlaciaItemFactory::questSigil('ascendent');
                    break;
                case 'eternal_sigil':
                    $item = GlaciaItemFactory::questSigil('eternal');
                    break;
                case 'heroic_slot_ticket':
                case 'heroicslotticket':
                case 'heroic_ticket':
                case 'heroicticket':
                case 'hslot':
                    $item = GlaciaItemFactory::heroicSlotTicket();
                    break;
                case 'item_flip_credit_voucher':
                case 'itemflipcreditvoucher':
                case 'if_credit_voucher':
                case 'icf_voucher':
                    $credits = isset($args['extra1']) ? max(1, (int) GeneralUtils::parseRandomNumber((string) $args['extra1'])) : 10;
                    $item = GlaciaItemFactory::itemFlipCreditVoucher($credits);
                    break;
                case 'exclusive_amplifier':
                case 'exclusiveamplifier':
                case 'amplifier':
                    $uses = isset($args['extra1']) ? max(1, (int) GeneralUtils::parseRandomNumber((string) $args['extra1'])) : 8;
                    $item = GlaciaItemFactory::exclusiveAmplifier($uses);
                    break;
                case 'holy_whitescroll':
                case 'holy_white_scroll':
                case 'holywhitescroll':
                    $item = GlaciaItemFactory::create('holy_whitescroll');
                    break;
                case 'holy_water':
                case 'holywater':
                    $item = GlaciaItemFactory::create('holy_water');
                    break;
                case 'charm_shard':
                case 'charmshard':
                    $success = isset($args['extra1']) ? (int) $args['extra1'] : null;
                    $bonus = isset($args['extra2']) ? max(0, (int) $args['extra2']) : 0;
                    $item = GlaciaItemFactory::charmShard($success, $bonus);
                    break;
                case 'charm_dust':
                case 'charmdust':
                    $bonus = isset($args['extra1']) ? max(1, (int) $args['extra1']) : null;
                    $item = GlaciaItemFactory::charmDust($bonus);
                    break;
                case 'conjuration_oracle':
                case 'conjuration_oracule':
                case 'oracle':
                    $item = GlaciaItemFactory::create('conjuration_oracle');
                    break;
                case 'blackscroll_dust':
                case 'black_scroll_dust':
                case 'holy_blackscroll_dust':
                case 'holy_black_scroll_dust':
                case 'bsdust':
                    $bonus = isset($args['extra1']) ? max(1, (int) $args['extra1']) : null;
                    $item = GlaciaItemFactory::blackscrollDust($bonus);
                    break;

                case 'essence':
                    $subId = isset($args['extra1']) ? strtolower(trim((string) $args['extra1'])) : '';
                    if ($subId === '' || !GlaciaItemFactory::hasDefinition('essence_' . $subId)) {
                        $sender->sendMessage(C::colorize("&r&cUsage: /giveitem essence <amount> <essence_id> [success 1-100]"));
                        return;
                    }
                    $succ = isset($args['extra2']) ? max(1, min(100, (int) $args['extra2'])) : null;
                    $item = GlaciaItemFactory::enchantEssence($subId, $succ);
                    break;

                case 'customarmor':
                    $set = $args['extra1'] ?? null;
                    $piece = $args['extra2'] ?? 'full';
                    $enchanted = strtolower((string)($args['extra3'] ?? 'false'));
                    $enchanted = in_array($enchanted, ['1', 'true', 'yes', 'enchanted'], true);

                    if ($set === null) {
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem customarmor <amount> <set> [helmet|chestplate|leggings|boots|full] [enchanted]"));
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
                        $sender->sendMessage(C::colorize("&cUsage: /giveitem customweapon <amount> <set> [sword|bow]"));
                        return;
                    }

                    $item = GlaciaItemFactory::customWeapon($set, $type);
                    break;
                default:
                    if (LootboxRepository::getBoxByLookup($itemName) !== null) {
                        $item = GlaciaItemFactory::lootbox($itemName, $sender);
                    } elseif (EtherealCrateRepository::getCrateByLookup($itemName) !== null) {
                        $item = GlaciaItemFactory::etherealCrate($itemName, $sender);
                    } elseif (GlaciaItemFactory::hasDefinition($itemName)) {
                        $item = GlaciaItemFactory::create($itemName);
                    } else {
                        $sender->sendMessage(C::colorize("&r&cUnknown Glacia item &f" . $itemName . "&c. Use &f/gi list&c or &f/gi menu&c."));
                        return;
                    }
                    break;
            }
        } catch (\InvalidArgumentException $e) {
            $sender->sendMessage(C::colorize("&cError: " . $e->getMessage()));
            return;
        }

        if (!empty($items)) {
            $sender->getInventory()->addItem(...$items);
            GlaciaUtils::logAdminAction("Give Item", [
                "admin" => $sender->getName(),
                "item_query" => $itemName,
                "amount" => $amount,
                "detail" => "multi_item",
            ]);
            $sender->sendMessage(C::colorize("&aYou have received the full armor set."));
            return;
        }

        if ($item !== null) {
            $item->setCount($amount);
            $sender->getInventory()->addItem($item);
            GlaciaUtils::logAdminAction("Give Item", [
                "admin" => $sender->getName(),
                "item_query" => $itemName,
                "amount" => $amount,
            ]);
            $sender->sendMessage(C::colorize("&aYou have received " . $item->getName()));
        } else {
            $sender->sendMessage(C::colorize("&cCould not create item: " . $itemName));
        }
    }

    private function parseMultiplier(string $input): float
    {
        $normalized = strtolower(trim($input));
        $normalized = str_replace("x", "", $normalized);

        return (float) $normalized;
    }

    private function resolveSigner(Player $sender, mixed $value): string
    {
        if (!is_string($value) || trim($value) === "") {
            return $sender->getName();
        }

        $normalized = strtolower(trim($value));
        if (in_array($normalized, ["default", "server", "hub"], true)) {
            return "Ethereal Hub";
        }

        return trim($value);
    }
}
