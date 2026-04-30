<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\enchants;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class UnEnchantSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("enchantment", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cConsole cannot remove enchants from a held item. Run this command in-game."));
            return;
        }

        $enchant = isset($args["enchantment"]) ? $args["enchantment"] : null;
        $item = $sender->getInventory()->getItemInHand();

        $enchantment = StringToEnchantmentParser::getInstance()->parse($enchant);
        if ($enchantment !== null) {
            if ($enchantment instanceof CustomEnchantment) {
                if ($item->getTypeId() !== VanillaItems::AIR()->getTypeId()) {
                    if ($item->hasEnchantment($enchantment)) {
                        $item->removeEnchantment($enchantment);
                        $sender->getInventory()->setItemInHand($item);
                        $sender->sendMessage(C::colorize(str_replace("{enchant}", ucfirst($enchantment->getName()), "&aRemoved enchant {enchant}!")));
                    } else {
                        $sender->sendMessage(C::colorize(str_replace("{enchant}", ucfirst($enchantment->getName()), "&cThat item doesn't have &4{enchant}&c!")));
                    }
                } else {
                    $sender->sendMessage(C::colorize("&cYou must be holding an item!"));
                }
            } else {
                $sender->sendMessage(C::colorize(str_replace("{enchant}", $enchant, "&c{enchant} is not a valid enchant!")));
            }
        }
    }

    public function getPermission(): ?string
    {
        return "glacia.unenchant";
    }
}