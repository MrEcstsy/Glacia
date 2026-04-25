<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\enchants\EnchantSubCommand;
use ecstsy\Glacia\commands\sub\enchants\GiveBookSubCommand;
use ecstsy\Glacia\commands\sub\enchants\GiveItemSubCommand;
use ecstsy\Glacia\commands\sub\enchants\GiveRCBookSubCommand;
use ecstsy\Glacia\commands\sub\enchants\InfoSubCommand;
use ecstsy\Glacia\commands\sub\enchants\ListSubCommand;
use ecstsy\Glacia\commands\sub\enchants\UnEnchantSubCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class EnchantmentsCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerSubCommand(new GiveItemSubCommand(Loader::getInstance(), "giveitem", "Give Plugin Items"));
        $this->registerSubCommand(new EnchantSubCommand(Loader::getInstance(), "enchant", "Enchant held item"));
        $this->registerSubCommand(new UnEnchantSubCommand(Loader::getInstance(), "unenchant", "Unenchant held item"));
        $this->registerSubCommand(new ListSubCommand(Loader::getInstance(), "list", "List all enchantments"));
        $this->registerSubCommand(new GiveBookSubCommand(Loader::getInstance(), "givebook", "Give enchantment book"));
        $this->registerSubCommand(new GiveBookSubCommand(Loader::getInstance(), "give", "Give enchantment book"));
        $this->registerSubCommand(new InfoSubCommand(Loader::getInstance(), "info", "Info about enchantment"));
        $this->registerSubCommand(new GiveRCBookSubCommand(Loader::getInstance(), "givercbook", "Give RC enchantment book"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $sender->sendMessage(C::colorize("&r&eGlacia Enchants"));
        $sender->sendMessage(C::colorize("&r&7/ge list [page] &f- browse enchantments"));
        $sender->sendMessage(C::colorize("&r&7/ge info <enchant> &f- view details"));
        $sender->sendMessage(C::colorize("&r&7/ge enchant <enchant> <level> &f- apply to held item"));
        $sender->sendMessage(C::colorize("&r&7/ge unenchant <enchant> &f- remove from held item"));
        $sender->sendMessage(C::colorize("&r&7/ge give <player> <enchant> <level> [amount] [success] [destroy] &f- give books"));
        $sender->sendMessage(C::colorize("&r&7/ge givercbook <type> <player> <amount> &f- give right-click books"));
        $sender->sendMessage(C::colorize("&r&7/ge giveitem <player> <item> <amount> &f- give utility items"));
    }

    public function getUsage(): string {
        return C::colorize("&cIncorrect command usage! Type '/ge' for the correct usage.");
    }
    public function getPermission(): string {
        return 'glacia.admin';
    }
}
