<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use ecstsy\Glacia\utils\screens\anticheat\AntiCheatInspectForm;
use ecstsy\Glacia\utils\screens\anticheat\AntiCheatInspectMenu;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use function array_values;
use function count;
use function sprintf;
use function strtolower;
use function trim;

final class AntiCheatCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("a", true));
        $this->registerArgument(1, new RawStringArgument("b", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $manager = AntiCheatManager::getInstance();
        $parts = array_values(array_filter(
            [trim((string) ($args["a"] ?? "")), trim((string) ($args["b"] ?? ""))],
            static fn(string $s): bool => $s !== ""
        ));

        if ($parts === []) {
            $this->sendHelp($sender);
            return;
        }

        $p0 = strtolower($parts[0]);

        if ($p0 === "help" || $p0 === "?") {
            $this->sendHelp($sender);
            return;
        }

        if ($p0 === "alerts" || $p0 === "notify") {
            if (!$sender instanceof Player) {
                $sender->sendMessage(C::colorize("&r&cAlerts toggle is in-game only."));
                return;
            }

            $enabled = $manager->toggleAlerts($sender);
            $sender->sendMessage(C::colorize($enabled
                ? "&r&aGuard &8> &aAlerts &fenabled&7; you will see Guard broadcasts."
                : "&r&cGuard &8> &cAlerts &fdisabled&7."));
            return;
        }

        if ($p0 === "form" || $p0 === "trust" || $p0 === "tf" || $p0 === "panel") {
            $this->handleFormInspect($sender, $parts[1] ?? null);
            return;
        }

        if ($p0 === "chest" || $p0 === "guiinv") {
            $this->handleChestInspect($sender, $parts[1] ?? null);
            return;
        }

        if ($p0 === "menu" || $p0 === "m" || $p0 === "gui" || $p0 === "inspect") {
            $this->handleMenu($sender, $parts[1] ?? null);
            return;
        }

        if ($p0 === "vl" || $p0 === "violations" || $p0 === "list") {
            $this->handleViolationsChat($sender, $parts[1] ?? null);
            return;
        }

        if ($p0 === "reset" || $p0 === "clear") {
            $this->handleReset($sender, $parts[1] ?? null);
            return;
        }

        $shortcut = PlayerUtils::getPlayerByPrefix($parts[0]);
        if ($shortcut instanceof Player && count($parts) === 1) {
            $this->openInspectFor($sender, $shortcut);
            return;
        }

        $this->handleViolationsChat($sender, $parts[0]);
    }

    private function sendHelp(CommandSender $sender): void {
        $sender->sendMessage(C::colorize("&r"));
        $sender->sendMessage(C::colorize("&8+--------------------------------------+"));
        $sender->sendMessage(C::colorize("&8| &b&lGlacia Guard &7- &fstaff toolkit &8|"));
        $sender->sendMessage(C::colorize("&8+--------------------------------------+"));
        $sender->sendMessage(C::colorize("&8| &f/ac menu &8- &7Inspect &8(&fchest vs form &7- &fyour /settings&8)"));
        $sender->sendMessage(C::colorize("&8| &f/ac form &8- &7Always mobile trust/risk panel"));
        $sender->sendMessage(C::colorize("&8| &f/ac chest &8- &7Always 4-row chest snapshot"));
        $sender->sendMessage(C::colorize("&8| &f/ac vl &8- &7Violation list &8(chat)"));
        $sender->sendMessage(C::colorize("&8| &f/ac alerts &8- &7Toggle live broadcasts"));
        $sender->sendMessage(C::colorize("&8| &f/ac reset <player> &8- &7Wipe seasonal Guard data"));
        $sender->sendMessage(C::colorize("&8+--------------------------------------+"));
        $sender->sendMessage(C::colorize("&r&8PE: &7/settings &8- &7Guard inspect &8- &7default form toggle"));
        $sender->sendMessage(C::colorize("&r&8Scores are &oheuristic&8; combine with evidence."));
    }

    private function handleMenu(CommandSender $sender, ?string $targetName): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cOpen the inspect GUI from in-game."));
            return;
        }

        $target = $targetName !== null && $targetName !== ""
            ? PlayerUtils::getPlayerByPrefix($targetName)
            : $sender;

        $this->openInspectFor($sender, $target);
    }

    private function handleFormInspect(CommandSender $sender, ?string $targetName): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cForms are in-game only."));
            return;
        }

        $target = $targetName !== null && $targetName !== ""
            ? PlayerUtils::getPlayerByPrefix($targetName)
            : $sender;

        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cThat player is not online."));
            return;
        }

        AntiCheatInspectForm::open($sender, $target);
    }

    private function handleChestInspect(CommandSender $sender, ?string $targetName): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cChest UI is in-game only."));
            return;
        }

        $target = $targetName !== null && $targetName !== ""
            ? PlayerUtils::getPlayerByPrefix($targetName)
            : $sender;

        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cThat player is not online."));
            return;
        }

        AntiCheatInspectMenu::openChest($sender, $target);
    }

    private function openInspectFor(CommandSender $staff, ?Player $target): void {
        if (!$staff instanceof Player) {
            $staff->sendMessage(C::colorize("&r&cPlayers only."));
            return;
        }

        if (!$target instanceof Player) {
            $staff->sendMessage(C::colorize("&r&cThat player is not online."));
            return;
        }

        AntiCheatInspectMenu::open($staff, $target);
    }

    private function handleViolationsChat(CommandSender $sender, ?string $targetName): void {
        $target = $sender instanceof Player && ($targetName === null || $targetName === "")
            ? $sender
            : PlayerUtils::getPlayerByPrefix((string) $targetName);

        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cUsage: &f/ac vl &7[&fplayer&7]"));
            return;
        }

        $manager = AntiCheatManager::getInstance();
        $violations = $manager->getViolations($target);
        $lag = $manager->assessLag($target);

        $sender->sendMessage(C::colorize("&r"));
        $sender->sendMessage(C::colorize("&8--- &bGuard VL &8| &f" . $target->getName() . " &8---"));
        $sender->sendMessage(C::colorize(sprintf(
            "&r&7Ping &f%dms &8- &7Jitter ~&f%.0fms &8- &7Lag x&f%.2f",
            $target->getNetworkSession()->getPing(),
            $lag->jitterMs,
            $lag->multiplier
        )));

        if ($violations === []) {
            $sender->sendMessage(C::colorize("&r&aNo active violation counters."));
            $sender->sendMessage(C::colorize("&r&8(Season memory may still exist in the inspect panel.)"));
            return;
        }

        foreach ($violations as $check => $vl) {
            $color = $this->vlColorCode($vl);
            $sender->sendMessage(C::colorize("&r " . $color . $check . " &8-> &f" . $vl));
        }

        $sender->sendMessage(C::colorize("&r&8Tip: &7/ac form " . $target->getName() . " &8- &7/ac chest " . $target->getName()));
    }

    private function handleReset(CommandSender $sender, ?string $targetName): void {
        if ($targetName === null || $targetName === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: &f/ac reset <player>"));
            return;
        }

        $target = PlayerUtils::getPlayerByPrefix($targetName);
        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cPlayer must be online."));
            return;
        }

        AntiCheatManager::getInstance()->reset($target);
        $sender->sendMessage(C::colorize("&r&aGuard &8> &7Wiped seasonal Guard data for &f" . $target->getName() . "&7."));
    }

    private function vlColorCode(int $vl): string {
        return match (true) {
            $vl >= 14 => "&c",
            $vl >= 8 => "&6",
            $vl >= 4 => "&e",
            default => "&7",
        };
    }

    public function getPermission(): string {
        return "glacia.anticheat.command";
    }
}
