<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\duels\DuelManager;
use ecstsy\Glacia\utils\screens\duels\DuelSetupScreen;
use ecstsy\Glacia\utils\screens\duels\DuelTargetScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class DuelCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("value", true));
        $this->registerArgument(1, new RawStringArgument("extra", true));
        $this->registerArgument(2, new RawStringArgument("third", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cOnly players can use duel commands."));
            return;
        }

        $manager = DuelManager::getInstance();
        $value = strtolower(trim((string) ($args["value"] ?? "")));
        $extra = trim((string) ($args["extra"] ?? ""));
        $third = trim((string) ($args["third"] ?? ""));

        if ($value === "") {
            DuelTargetScreen::display($sender);
            return;
        }

        switch ($value) {
            case "accept":
                $manager->acceptRequest($sender, $extra !== "" ? $extra : null);
                return;

            case "deny":
                $manager->denyRequest($sender, $extra !== "" ? $extra : null);
                return;

            case "end":
                $manager->requestMutualEnd($sender);
                return;

            case "stats":
                $lookup = $extra !== "" ? $extra : $sender->getName();
                $line = $manager->getStatsLine($lookup);
                if ($line === null) {
                    $sender->sendMessage(C::colorize("&r&cNo duel stats were found for that player."));
                    return;
                }
                $sender->sendMessage($line);
                return;

            case "admin":
                if (!$sender->hasPermission("glacia.admin")) {
                    $sender->sendMessage(C::colorize("&r&cYou do not have permission to do that."));
                    return;
                }
                $this->handleAdmin($sender, strtolower($extra), $third);
                return;
        }

        $target = PlayerUtils::getPlayerByPrefix($value);
        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cPlayer not found."));
            return;
        }

        DuelSetupScreen::display($sender, $target);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }

    private function handleAdmin(Player $sender, string $action, string $third): void
    {
        $manager = DuelManager::getInstance();

        switch ($action) {
            case "":
            case "info":
                $sender->sendMessage($manager->formatArenaSummary());
                $active = $manager->getActiveDuelSummary();
                if ($active !== null) {
                    $sender->sendMessage(C::colorize(
                        "&r&eActive Duel &7" . $active["challenger"] . " &fvs &7" . $active["target"] .
                        " &8| &7Mode: &f" . $active["loadout"] .
                        " &8| &7Remaining: &f" . $active["remaining"] . "s"
                    ));
                }
                return;

            case "setcenter":
                $manager->setArenaCenter($sender->getLocation());
                $sender->sendMessage(C::colorize("&r&aDuel arena center updated from your current position."));
                return;

            case "setradius":
                if ($third === "" || !is_numeric($third)) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /duel admin setradius <radius>"));
                    return;
                }
                $manager->setArenaRadius((float) $third);
                $sender->sendMessage(C::colorize("&r&aDuel arena radius set to &f" . $third . "&a."));
                return;

            case "setarena":
                if ($third === "" || !is_numeric($third)) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /duel admin setarena <radius>"));
                    return;
                }
                $manager->setArenaCenter($sender->getLocation());
                $manager->setArenaRadius((float) $third);
                $manager->setArenaHeightBand((int) floor($sender->getLocation()->y), (int) floor($sender->getLocation()->y) + 19);
                $sender->sendMessage(C::colorize("&r&aDuel arena center and radius were updated from your current position."));
                return;

            case "setspawn1":
                $manager->setArenaSpawn(1, $sender->getLocation());
                $sender->sendMessage(C::colorize("&r&aDuel spawn 1 set."));
                return;

            case "setspawn2":
                $manager->setArenaSpawn(2, $sender->getLocation());
                $sender->sendMessage(C::colorize("&r&aDuel spawn 2 set."));
                return;

            case "tp":
                $center = $manager->getArenaCenter();
                if ($center === null) {
                    $sender->sendMessage(C::colorize("&r&cThe duel arena is not configured."));
                    return;
                }
                $sender->teleport($center);
                $sender->sendMessage(C::colorize("&r&aTeleported to the duel arena center."));
                return;

            case "forceend":
                $targetName = trim($third);
                if ($targetName === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /duel admin forceend <player>"));
                    return;
                }
                if (!$manager->forceEndByPlayerName($targetName)) {
                    $sender->sendMessage(C::colorize("&r&cNo active duel matched that player."));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&aThe active duel was force-ended."));
                return;
        }

        $sender->sendMessage(C::colorize("&r&6Duel Admin Commands"));
        $sender->sendMessage(C::colorize("&r&e/duel admin info"));
        $sender->sendMessage(C::colorize("&r&e/duel admin setcenter"));
        $sender->sendMessage(C::colorize("&r&e/duel admin setradius <radius>"));
        $sender->sendMessage(C::colorize("&r&e/duel admin setarena <radius>"));
        $sender->sendMessage(C::colorize("&r&e/duel admin setspawn1"));
        $sender->sendMessage(C::colorize("&r&e/duel admin setspawn2"));
        $sender->sendMessage(C::colorize("&r&e/duel admin tp"));
        $sender->sendMessage(C::colorize("&r&e/duel admin forceend <player>"));
    }
}
