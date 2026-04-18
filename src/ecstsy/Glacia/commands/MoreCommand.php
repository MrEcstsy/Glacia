<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class MoreCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new IntegerArgument("amount", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $item = $sender->getInventory()->getItemInHand();
        if ($item->isNull()) {
            $sender->sendMessage(C::colorize("&r&cYou must be holding an item."));
            return;
        }

        $amount = max(1, min(64, (int) ($args["amount"] ?? 64)));
        $item->setCount($amount);
        $sender->getInventory()->setItemInHand($item);
        $sender->sendMessage(C::colorize("&r&aUpdated your held item stack to &f{$amount}&a."));

        GlaciaUtils::logAdminAction("More", [
            "admin" => $sender->getName(),
            "item" => $item->getName(),
            "amount" => $amount
        ]);
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
