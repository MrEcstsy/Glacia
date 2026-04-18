<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ClearInventoryCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $target = null;

        if (isset($args["player"]) && is_string($args["player"])) {
            $target = PlayerUtils::getPlayerByPrefix($args["player"]);
            if (!$target instanceof Player) {
                $sender->sendMessage(C::colorize("&r&cThat player could not be found."));
                return;
            }
        } elseif ($sender instanceof Player) {
            $target = $sender;
        }

        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cUsage: /clearinventory <player>"));
            return;
        }

        $target->getInventory()->clearAll();
        $target->getArmorInventory()->clearAll();
        $target->getOffHandInventory()->clearAll();

        if ($target === $sender) {
            $sender->sendMessage(C::colorize("&r&aCleared your inventory."));
        } else {
            $sender->sendMessage(C::colorize("&r&aCleared &f" . $target->getName() . "&a's inventory."));
            $target->sendMessage(C::colorize("&r&cYour inventory was cleared by &f" . $sender->getName() . "&c."));
        }

        GlaciaUtils::logAdminAction("Clear Inventory", [
            "admin" => $sender->getName(),
            "target" => $target->getName()
        ]);
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
