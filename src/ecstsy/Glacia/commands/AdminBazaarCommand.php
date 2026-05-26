<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\args\RawStringArgument;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\adminbazaar\AdminBazaarManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\screens\adminbazaar\AdminBazaarScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

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

        if (in_array($action, ["buy", "purchase"], true)) {
            AdminBazaarManager::purchase($sender);
            return;
        }

        if (in_array($action, ["balance", "bal", "coins"], true)) {
            $session = Loader::getPlayerManager()->getSession($sender);
            $sender->sendMessage(GlaciaUtils::format("adminbazaar.command.balance", [
                "amount" => number_format((int) ($session?->getAdminCoins() ?? 0)),
            ]));
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
                $sender->sendMessage(GlaciaUtils::format("adminbazaar.command.reroll.no_permission"));
                return;
            }

            $forcedKey = strtolower(trim((string) ($args["arg1"] ?? "")));
            if (!AdminBazaarManager::rollNextListing(true, $forcedKey !== "" ? $forcedKey : null)) {
                $sender->sendMessage(GlaciaUtils::format("adminbazaar.command.reroll.failed"));
                return;
            }

            $sender->sendMessage(GlaciaUtils::format("adminbazaar.command.reroll.success"));
            return;
        }

        if ($action === "grant" || $action === "take" || $action === "set") {
            if (!$sender->hasPermission("glacia.admin")) {
                $sender->sendMessage(GlaciaUtils::format("adminbazaar.command.modify.no_permission"));
                return;
            }

            $targetArg = (string) ($args["arg1"] ?? "");
            $amount = max(0, (int) ($args["arg2"] ?? 0));
            $target = PlayerUtils::getPlayerByPrefix($targetArg);
            if (!$target instanceof Player) {
                $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => $targetArg]));
                return;
            }

            $session = Loader::getPlayerManager()->getSession($target);
            if ($session === null) {
                $sender->sendMessage(GlaciaUtils::format("player.error.no_session"));
                return;
            }

            match ($action) {
                "grant" => $session->addAdminCoins($amount),
                "take" => $session->removeAdminCoins($amount),
                "set" => $session->setAdminCoins($amount),
            };

            $sender->sendMessage(GlaciaUtils::format("adminbazaar.command.modify.sender", [
                "player" => $target->getName(),
                "amount" => number_format($session->getAdminCoins()),
            ]));
            if ($target !== $sender) {
                $target->sendMessage(GlaciaUtils::format("adminbazaar.command.modify.target", [
                    "amount" => number_format($session->getAdminCoins()),
                ]));
            }
            return;
        }

        $sender->sendMessage(GlaciaUtils::format("adminbazaar.help.open"));
        $sender->sendMessage(GlaciaUtils::format("adminbazaar.help.buy"));
        $sender->sendMessage(GlaciaUtils::format("adminbazaar.help.balance"));
        $sender->sendMessage(GlaciaUtils::format("adminbazaar.help.status"));
        if ($sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(GlaciaUtils::format("adminbazaar.help.reroll"));
            $sender->sendMessage(GlaciaUtils::format("adminbazaar.help.manage"));
        }
    }

    public function getPermission() : string
    {
        return "glacia.default";
    }
}
