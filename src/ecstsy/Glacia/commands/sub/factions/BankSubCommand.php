<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class BankSubCommand extends BaseSubCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("amount", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        $faction = $session?->getFaction();
        if ($faction === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $manager = Loader::getFactionUpgradeManager();
        $manager->ensureFaction($faction);

        $action = strtolower((string) ($args["action"] ?? ""));
        if ($action === "") {
            $sender->sendMessage(C::colorize("&r&bFaction Bank: &a$" . number_format($manager->getBankBalance($faction->getFactionId()), 2)));
            $sender->sendMessage(C::colorize("&r&7Use &b/f bank deposit <amount> &7to contribute."));
            $sender->sendMessage(C::colorize("&r&7Use &b/f bank withdraw <amount> &7to withdraw as a leader or officer."));
            $sender->sendMessage(C::colorize("&r&7Use &b/f upgrades &7to spend banked money on upgrades."));
            return;
        }

        $amount = $this->parseAmount((string) ($args["amount"] ?? ""));
        if (!in_array($action, ["deposit", "withdraw"], true) || $amount <= 0) {
            $sender->sendMessage(C::colorize("&r&cUsage: /f bank <deposit|withdraw> <amount>"));
            return;
        }

        if ($action === "deposit") {
            if ($session->getBalance() < $amount) {
                $sender->sendMessage(C::colorize("&r&cYou do not have enough money to deposit &f$" . number_format($amount) . "&c."));
                return;
            }

            $session->removeBalance($amount);
            $manager->deposit($faction, $amount);
            $sender->sendMessage(C::colorize("&r&aDeposited &f$" . number_format($amount) . " &ainto the faction bank."));
            return;
        }

        if (!in_array($session->getFactionRole(), ["leader", "officer"], true)) {
            $sender->sendMessage(C::colorize("&r&cOnly faction leaders and officers can withdraw from the faction bank."));
            return;
        }

        if (!$manager->withdraw($faction, $amount)) {
            $sender->sendMessage(C::colorize("&r&cYour faction bank does not have enough money."));
            return;
        }

        $session->addBalance($amount);
        $sender->sendMessage(C::colorize("&r&aWithdrawn &f$" . number_format($amount) . " &afrom the faction bank."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }

    private function parseAmount(string $input): int
    {
        $input = strtolower(trim(str_replace([",", "$"], "", $input)));
        if ($input === "") {
            return 0;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)([kmb])?$/', $input, $matches) !== 1) {
            return 0;
        }

        $value = (float) $matches[1];
        $suffix = $matches[2] ?? "";
        $multiplier = match ($suffix) {
            "k" => 1_000,
            "m" => 1_000_000,
            "b" => 1_000_000_000,
            default => 1,
        };

        return (int) round($value * $multiplier);
    }
}
