<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class WithdrawCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("amount"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $input = (string) ($args["amount"] ?? "");
        $amount = $this->parseAmount($input);
        if ($amount <= 0) {
            $sender->sendMessage(C::colorize("&r&cUsage: /withdraw <amount>"));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null) {
            return;
        }

        if ($session->getBalance() < $amount) {
            $sender->sendMessage(C::colorize("&r&cYou do not have enough money to withdraw &f$" . number_format($amount) . "&c."));
            return;
        }

        $session->removeBalance($amount);
        $note = GlaciaItemFactory::bankNote($sender, $amount);
        $leftovers = $sender->getInventory()->addItem($note);

        if ($leftovers !== []) {
            foreach ($leftovers as $leftover) {
                $session->addItemToCollection($leftover);
            }
            $sender->sendMessage(C::colorize("&r&aWithdrawn &f$" . number_format($amount) . "&a as a bank note. Overflow was sent to /collect."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&aWithdrawn &f$" . number_format($amount) . "&a as a bank note."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }

    private function parseAmount(string $input): int
    {
        return GlaciaUtils::parseShorthandNumber($input);
    }
}
