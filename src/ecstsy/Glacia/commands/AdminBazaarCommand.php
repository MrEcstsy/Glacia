<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\args\RawStringArgument;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\adminbazaar\AdminBazaarManager;
use ecstsy\Glacia\utils\screens\adminbazaar\AdminBazaarScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AdminBazaarCommand extends BaseCommand
{
    protected function prepare() : void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("arg1", true));
        $this->registerArgument(2, new RawStringArgument("arg2", true));
        $this->registerArgument(3, new RawStringArgument("arg3", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $action = strtolower(trim((string) ($args["action"] ?? "")));
        if ($action === "" || in_array($action, ["open", "menu"], true)) {
            AdminBazaarScreen::display($sender);
            return;
        }

        if (in_array($action, ["balance", "bal", "coins"], true)) {
            $session = Loader::getPlayerManager()->getSession($sender);
            $sender->sendMessage(C::colorize("&r&6Admin Coins: &f" . number_format((int) ($session?->getAdminCoins() ?? 0))));
            return;
        }

        if ($action === "status") {
            foreach (AdminBazaarManager::getStatusLines() as $line) {
                $sender->sendMessage($line);
            }
            return;
        }

        if ($action === "reroll") {
            if (!$sender->hasPermission("glacia.admin")) {
                $sender->sendMessage(C::colorize("&r&cYou do not have permission to reroll the Admin Bazaar."));
                return;
            }

            $forcedKey = strtolower(trim((string) ($args["arg1"] ?? "")));
            if (!AdminBazaarManager::rollNextListing(true, $forcedKey !== "" ? $forcedKey : null)) {
                $sender->sendMessage(C::colorize("&r&cFailed to roll an Admin Bazaar listing."));
                return;
            }

            $sender->sendMessage(C::colorize("&r&aAdmin Bazaar listing rerolled."));
            return;
        }

        if ($action === "grant" || $action === "take" || $action === "set") {
            if (!$sender->hasPermission("glacia.admin")) {
                $sender->sendMessage(C::colorize("&r&cYou do not have permission to modify Admin Coins."));
                return;
            }

            $targetArg = (string) ($args["arg1"] ?? "");
            $amount = max(0, (int) ($args["arg2"] ?? 0));
            $target = PlayerUtils::getPlayerByPrefix($targetArg);
            if (!$target instanceof Player) {
                $sender->sendMessage(C::colorize("&r&cThat player is not online."));
                return;
            }

            $session = Loader::getPlayerManager()->getSession($target);
            if ($session === null) {
                $sender->sendMessage(C::colorize("&r&cThat player's session is unavailable."));
                return;
            }

            match ($action) {
                "grant" => $session->addAdminCoins($amount),
                "take" => $session->removeAdminCoins($amount),
                "set" => $session->setAdminCoins($amount),
            };

            $sender->sendMessage(C::colorize("&r&aUpdated &f{$target->getName()}&a's Admin Coins to &f" . number_format($session->getAdminCoins()) . "&a."));
            if ($target !== $sender) {
                $target->sendMessage(C::colorize("&r&6Your Admin Coin balance is now &f" . number_format($session->getAdminCoins()) . "&6."));
            }
            return;
        }

        $sender->sendMessage(C::colorize("&r&d/ab &7- open the Admin Bazaar"));
        $sender->sendMessage(C::colorize("&r&d/ab balance &7- view your Admin Coins"));
        $sender->sendMessage(C::colorize("&r&d/ab status &7- view listing timers"));
        if ($sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&d/ab reroll [key] &7- reroll the listing"));
            $sender->sendMessage(C::colorize("&r&d/ab grant|take|set <player> <amount> &7- manage Admin Coins"));
        }
    }

    public function getPermission() : string
    {
        return "glacia.default";
    }
}
