<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class BountyCommand extends BaseCommand
{
    private const PER_PAGE = 10;
    private const MIN_BOUNTY = 1000;

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("target", true));
        $this->registerArgument(1, new RawStringArgument("amount", true));
        $this->registerArgument(2, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $targetArg = strtolower((string) ($args["target"] ?? ""));
        if ($targetArg === "") {
            $sender->sendMessage(C::colorize("&r&6Bounty Commands"));
            $sender->sendMessage(C::colorize("&r&e/bounty <player> &7View a player's current bounty."));
            $sender->sendMessage(C::colorize("&r&e/bounty <player> <amount> &7Place money on their head."));
            $sender->sendMessage(C::colorize("&r&e/bounty top [page] &7View the highest active bounties."));
            if ($sender->hasPermission("glacia.admin")) {
                $sender->sendMessage(C::colorize("&r&e/bounty clear <player> &7Remove a player's bounty."));
            }
            return;
        }

        if ($targetArg === "top") {
            $this->showTop($sender, max(1, (int) ($args["amount"] ?? $args["page"] ?? 1)));
            return;
        }

        if ($targetArg === "clear") {
            if (!$sender->hasPermission("glacia.admin")) {
                $sender->sendMessage(C::colorize("&r&cYou do not have permission to clear bounties."));
                return;
            }

            $target = $this->resolveSession((string) ($args["amount"] ?? ""));
            if ($target === null) {
                $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => (string) ($args["amount"] ?? "")], "error"));
                return;
            }

            Loader::getBountyManager()->clearBounty($target);
            $sender->sendMessage(C::colorize("&r&aCleared the bounty on &f" . $target->getUsername() . "&a."));
            GlaciaUtils::logAdminAction("Bounty Cleared", [
                "Staff" => $sender->getName(),
                "Target" => $target->getUsername(),
            ]);
            return;
        }

        $target = $this->resolveSession((string) ($args["target"] ?? ""));
        if ($target === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => (string) ($args["target"] ?? "")], "error"));
            return;
        }

        $amountArg = $args["amount"] ?? null;
        if ($amountArg === null) {
            $current = Loader::getBountyManager()->getBounty($target);
            $sender->sendMessage(C::colorize("&r&6Bounty &8| &r&f" . $target->getUsername() . "&7 currently has &a$" . number_format($current) . " &7on their head."));
            return;
        }

        if ($target->getUuid()->toString() === $sender->getUniqueId()->toString()) {
            $sender->sendMessage(C::colorize("&r&cYou can't place a bounty on yourself."));
            return;
        }

        $amount = (int) GeneralUtils::parseRandomNumber((string) $amountArg);
        if ($amount < self::MIN_BOUNTY) {
            $sender->sendMessage(C::colorize("&r&cMinimum bounty is $" . number_format(self::MIN_BOUNTY) . "."));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null || $session->getBalance() < $amount) {
            $sender->sendMessage(C::colorize("&r&cYou don't have enough money to place that bounty."));
            return;
        }

        $session->removeBalance($amount);
        $newTotal = Loader::getBountyManager()->setBounty($target, $amount);
        Loader::getBountyManager()->broadcastPlacement($sender->getName(), $target->getUsername(), $newTotal);
        $sender->sendMessage(C::colorize("&r&aYou placed &f$" . number_format($amount) . " &aon &f" . $target->getUsername() . "&a."));
    }

    private function showTop(Player $sender, int $page): void
    {
        $totalEntries = Loader::getBountyManager()->getCount();
        $totalPages = max(1, (int) ceil($totalEntries / self::PER_PAGE));
        $page = min(max(1, $page), $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;
        $rows = Loader::getBountyManager()->getTopBounties(self::PER_PAGE, $offset);

        $sender->sendMessage(C::colorize("&r&6Top Bounties &8(&f{$page}&7/&f{$totalPages}&8)"));
        if ($rows === []) {
            $sender->sendMessage(C::colorize("&r&7No active bounties right now."));
            return;
        }

        foreach ($rows as $index => $row) {
            $rank = $offset + $index + 1;
            $sender->sendMessage(C::colorize("&r&e{$rank}. &f{$row['name']}&7: &a$" . number_format((int) $row["amount"])));
        }
    }

    private function resolveSession(string $lookup): ?\ecstsy\Glacia\player\GlaciaPlayer
    {
        if ($lookup === "") {
            return null;
        }

        $online = PlayerUtils::getPlayerByPrefix($lookup);
        if ($online !== null) {
            return Loader::getPlayerManager()->getSession($online);
        }

        return Loader::getPlayerManager()->getSessionByPrefix($lookup);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
