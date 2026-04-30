<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\enchants;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GiveBookSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->setPermissionMessage(C::colorize("&r&cYou don't have permission to do that!"));
        $this->registerArgument(0, new RawStringArgument("name", false));
        $this->registerArgument(1, new RawStringArgument("enchantment", false));
        $this->registerArgument(2, new IntegerArgument("level", false));
        $this->registerArgument(3, new IntegerArgument("amount", true));
        $this->registerArgument(4, new IntegerArgument("success", true));
        $this->registerArgument(5, new IntegerArgument("destroy", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $player = isset($args["name"]) ? PlayerUtils::getPlayerByPrefix($args["name"]) : null;
        if ($player === null) {
            $sender->sendMessage(C::colorize("&r&cThat player is not online!"));
            return;
        }

        $enchant = $args["enchantment"] ?? null;
        if ($enchant === null) {
            $sender->sendMessage(C::colorize("&r&cUsage: " . $this->getUsage()));
            return;
        }

        $level = $args["level"] ?? 1;
        $enchantment = CustomEnchantments::getEnchantmentByName($enchant);
        if ($enchantment === null) {
            $sender->sendMessage(C::colorize("&cInvalid enchantment!"));
            return;
        }

        $amount = $args["amount"] ?? 1;
        $success = $args["success"] ?? null;
        $destroy = $args["destroy"] ?? null;

        $book = GlaciaItemFactory::enchantmentBook($enchantment, $level, $success, $destroy)->setCount($amount);

        if ($player->getInventory()->canAddItem($book)) {
            $player->getInventory()->addItem($book);
            $sender->sendMessage(C::colorize(str_replace(
                ["{enchant}", "{level}", "{player}", "{amount}"],
                [$enchant, $level, $player->getName(), $amount],
                "&aGave &2{player} {amount}x {enchant} {level} &abooks!"
            )));
            if ($sender instanceof Player) {
                PlayerUtils::playSound($sender, "random.orb");
            }
        } else {
            $player->getWorld()->dropItem($player->getPosition()->asVector3(), $book);
            $sender->sendMessage(C::colorize("&r&e" . $player->getName() . "'s inventory was full, so the book was dropped at their feet."));
        }
    }

    public function getUsage(): string {
        return "/ge givebook <player> <enchant> <level> [amount] [success] [destroy]";
    }

    public function getPermission(): ?string
    {
        return "glacia.give-book";
    }
}
