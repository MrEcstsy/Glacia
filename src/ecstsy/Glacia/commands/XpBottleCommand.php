<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\exp\XpBottleAllSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\color\Color;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class XpBottleCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("amount", false));

        $this->registerSubCommand(new XpBottleAllSubCommand(Loader::getInstance(), "all", "Put all your XP into a bottle"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $amount = GeneralUtils::parseShorthandNumber((string) ($args["amount"] ?? ""));

        if ($amount <= 0) {
            $sender->sendMessage(C::colorize("&r&cError: &4Amount must be greater than 0."));
            return;
        }

        $currentXP = $sender->getXpManager()->getCurrentTotalXp();

        if ($amount > $currentXP) {
            $sender->sendMessage(C::colorize("&r&cError: &4You don't have enough XP."));
            return;
        }

        $sender->getXpManager()->setCurrentTotalXp($currentXP - $amount);

        $item = GlaciaItemFactory::xpNote($sender, $amount);
        $sender->getInventory()->addItem($item);

        $sender->sendMessage(C::colorize("&r&aYou have put " . number_format($amount) . " XP into a bottle."));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
