<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\gkits\GKitRepository;
use ecstsy\Glacia\utils\screens\kits\KitFormScreen;
use ecstsy\Glacia\utils\screens\kits\GKitScreen;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GKitCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("value", true));
        $this->registerArgument(1, new RawStringArgument("extra", true));
        $this->registerArgument(2, new RawStringArgument("third", true));
        $this->registerArgument(3, new RawStringArgument("fourth", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $value = strtolower((string) ($args["value"] ?? ""));
        $extra = (string) ($args["extra"] ?? "");
        $third = (string) ($args["third"] ?? "");
        $fourth = (string) ($args["fourth"] ?? "");

        if ($value === "") {
            if ($sender instanceof Player) {
                $session = Loader::getPlayerManager()->getSession($sender);
                if ($session !== null && $session->getSetting("chest_inventories") === false) {
                    KitFormScreen::displayGKits($sender);
                    return;
                }

                GKitScreen::display($sender);
                return;
            }

            $sender->sendMessage("/gkits <info|give|givegem|givesummoner|setlevel|remove|resetcooldown>");
            return;
        }

        if ($value === "info") {
            $target = $extra !== "" ? PlayerUtils::getPlayerByPrefix($extra) : ($sender instanceof Player ? $sender : null);
            $session = $target !== null ? Loader::getPlayerManager()->getSession($target) : Loader::getPlayerManager()->getSessionByPrefix($extra);
            if ($session === null) {
                $sender->sendMessage(C::colorize("&r&cPlayer not found."));
                return;
            }

            $sender->sendMessage(C::colorize("&r&bGKit Levels &7for &f" . $session->getUsername()));
            foreach (GKitRepository::getGKits() as $kit) {
                $level = GKitRepository::getLevel($session, (string) $kit["id"]);
                $sender->sendMessage(C::colorize("&r&7- &f" . C::clean((string) $kit["display"]) . "&7: &b" . $level . "&7/&f" . GKitRepository::getMaxLevel($kit)));
            }
            return;
        }

        if (!$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to do that."));
            return;
        }

        $target = $extra !== "" ? PlayerUtils::getPlayerByPrefix($extra) : null;
        $targetSession = $target !== null ? Loader::getPlayerManager()->getSession($target) : ($extra !== "" ? Loader::getPlayerManager()->getSessionByPrefix($extra) : null);
        if ($targetSession === null && $extra !== "") {
            $sender->sendMessage(C::colorize("&r&cThat player profile could not be found."));
            return;
        }

        $kit = $third !== "" ? GKitRepository::getGKitById($third) : null;

        switch ($value) {
            case "give":
                if (!$target instanceof Player || $kit === null) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /gkits give <player> <gkit>"));
                    return;
                }
                if (!GKitRepository::give($target, $kit, max(1, GKitRepository::getLevel(Loader::getPlayerManager()->getSession($target), (string) $kit["id"])))) {
                    $sender->sendMessage(C::colorize("&r&cFailed to give that GKit."));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&aGave &f" . ((string) $kit["display"]) . "&r&a to &f" . $target->getName() . "&a."));
                return;

            case "givegem":
                if (!$target instanceof Player || $kit === null) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /gkits givegem <player> <gkit> [amount]"));
                    return;
                }
                $amount = max(1, (int) GeneralUtils::parseRandomNumber($fourth !== "" ? $fourth : "1"));
                $target->getInventory()->addItem(GKitRepository::createGem($kit)->setCount($amount));
                $sender->sendMessage(C::colorize("&r&aGave &f{$amount}x&r&a gem(s) for &f" . ((string) $kit["display"]) . "&r&a to &f" . $target->getName() . "&a."));
                return;

            case "givesummoner":
                if (!$target instanceof Player || $kit === null) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /gkits givesummoner <player> <gkit> [amount]"));
                    return;
                }
                $amount = max(1, (int) GeneralUtils::parseRandomNumber($fourth !== "" ? $fourth : "1"));
                $target->getInventory()->addItem(GKitRepository::createSummoner($kit)->setCount($amount));
                $sender->sendMessage(C::colorize("&r&aGave &f{$amount}x&r&a summoner(s) for &f" . ((string) $kit["display"]) . "&r&a to &f" . $target->getName() . "&a."));
                return;

            case "setlevel":
                if ($targetSession === null || $kit === null || $fourth === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /gkits setlevel <player> <gkit> <level>"));
                    return;
                }
                $level = max(0, min(GKitRepository::getMaxLevel($kit), (int) GeneralUtils::parseRandomNumber($fourth)));
                GKitRepository::setLevel($targetSession, (string) $kit["id"], $level);
                $sender->sendMessage(C::colorize("&r&aSet &f" . $targetSession->getUsername() . "&a's &f" . C::clean((string) $kit["display"]) . "&a level to &f" . $level . "&a."));
                return;

            case "remove":
                if ($targetSession === null || $kit === null) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /gkits remove <player> <gkit>"));
                    return;
                }
                GKitRepository::setLevel($targetSession, (string) $kit["id"], 0);
                $sender->sendMessage(C::colorize("&r&aRemoved &f" . C::clean((string) $kit["display"]) . "&a from &f" . $targetSession->getUsername() . "&a."));
                return;

            case "resetcooldown":
                if (!$target instanceof Player || $kit === null) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /gkits resetcooldown <player> <gkit>"));
                    return;
                }
                GKitRepository::clearCooldown($target, $kit);
                $sender->sendMessage(C::colorize("&r&aReset &f" . $target->getName() . "&a's cooldown for &f" . C::clean((string) $kit["display"]) . "&a."));
                return;
        }

        $sender->sendMessage(C::colorize("&r&6GKit Commands"));
        $sender->sendMessage(C::colorize("&r&e/gkits &7Open the GKit menu."));
        $sender->sendMessage(C::colorize("&r&e/gkits info [player] &7View GKit levels."));
        if ($sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&e/gkits give <player> <gkit>"));
            $sender->sendMessage(C::colorize("&r&e/gkits givegem <player> <gkit> [amount]"));
            $sender->sendMessage(C::colorize("&r&e/gkits givesummoner <player> <gkit> [amount]"));
            $sender->sendMessage(C::colorize("&r&e/gkits setlevel <player> <gkit> <level>"));
            $sender->sendMessage(C::colorize("&r&e/gkits remove <player> <gkit>"));
            $sender->sendMessage(C::colorize("&r&e/gkits resetcooldown <player> <gkit>"));
        }
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
