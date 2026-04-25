<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\screens\vaults\PlayerVaultScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PlayerVaultCommand extends BaseCommand {

    public function prepare() : void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new TextArgument("input", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cUse this command in-game."));
            return;
        }

        $input = trim((string) ($args["input"] ?? ""));
        if ($input === "") {
            $this->sendInstructions($sender);
            return;
        }

        $parts = preg_split('/\s+/', $input) ?: [];
        $first = strtolower((string) array_shift($parts));

        if (ctype_digit($first)) {
            $vaultNumber = (int) $first;
            $targetName = trim(implode(" ", $parts));

            if ($targetName !== "") {
                $this->openOtherVault($sender, $vaultNumber, $targetName);
                return;
            }

            $this->openOwnVault($sender, $vaultNumber);
            return;
        }

        if ($first === "search") {
            $query = trim(implode(" ", $parts));
            if ($query === "") {
                $sender->sendMessage(C::colorize("&r&cUsage: /pv search <query>"));
                return;
            }

            $this->searchVaults($sender, $query);
            return;
        }

        if ($first === "list") {
            $targetName = trim(implode(" ", $parts));
            if ($targetName === "") {
                $this->listVaults($sender);
                return;
            }

            $this->listOtherVaults($sender, $targetName);
            return;
        }

        $this->sendInstructions($sender);
    }

    private function openOwnVault(Player $player, int $vaultNumber) : void
    {
        if ($vaultNumber <= 0) {
            $player->sendMessage(C::colorize("&r&cVault numbers must be positive."));
            return;
        }

        if (!Loader::getPlayerVaultManager()->canAccessVault($player, $vaultNumber)) {
            $player->sendMessage(C::colorize("&r&cYou do not have access to vault &f#" . $vaultNumber . "&r&c."));
            return;
        }

        if (!Loader::getPlayerVaultManager()->claimOpenEditor($player->getUniqueId()->toString(), $vaultNumber, $player)) {
            $player->sendMessage(C::colorize("&r&cThat vault is already open."));
            return;
        }

        (new PlayerVaultScreen(
            $player,
            $player->getUniqueId()->toString(),
            $player->getName(),
            $vaultNumber
        ))->display($player);
    }

    private function searchVaults(Player $player, string $query) : void
    {
        $manager = Loader::getPlayerVaultManager();
        $vaultNumbers = $manager->hasInfiniteAccess($player)
            ? $manager->getKnownVaultNumbers($player->getUniqueId()->toString())
            : $manager->getAccessibleVaults($player);

        if ($vaultNumbers === []) {
            $player->sendMessage(C::colorize("&r&cYou do not have any searchable vaults yet."));
            return;
        }

        $matches = $manager->searchVaults($player->getUniqueId()->toString(), $vaultNumbers, $query);
        if ($matches === []) {
            $player->sendMessage(C::colorize("&r&cNo accessible vaults contain items matching &f" . $query . "&r&c."));
            return;
        }

        $player->sendMessage(C::colorize("&r&bVault search matches for &f" . $query . "&r&b:"));
        foreach ($matches as $vaultNumber => $count) {
            $player->sendMessage(C::colorize("&r&7- &f/pv {$vaultNumber} &r&8(&b{$count} match" . ($count === 1 ? "" : "es") . "&8)"));
        }
    }

    private function openOtherVault(Player $viewer, int $vaultNumber, string $targetName) : void
    {
        if (!$viewer->hasPermission("glacia.admin")) {
            $viewer->sendMessage(C::colorize("&r&cYou do not have permission to view other players' vaults."));
            return;
        }

        if ($vaultNumber <= 0) {
            $viewer->sendMessage(C::colorize("&r&cVault numbers must be positive."));
            return;
        }

        $target = PlayerUtils::getPlayerByPrefix($targetName);
        $session = $target !== null ? Loader::getPlayerManager()->getSession($target) : Loader::getPlayerVaultManager()->resolvePlayerSession($targetName);
        if ($session === null) {
            $viewer->sendMessage(C::colorize("&r&cThat player could not be found."));
            return;
        }

        (new PlayerVaultScreen(
            $viewer,
            $session->getUuid()->toString(),
            $session->getUsername(),
            $vaultNumber,
            true
        ))->display($viewer);
    }

    private function listVaults(Player $player) : void
    {
        $manager = Loader::getPlayerVaultManager();
        $nonEmpty = $manager->getNonEmptyVaultNumbers($player->getUniqueId()->toString(), $player);

        if ($nonEmpty === []) {
            $player->sendMessage(C::colorize("&r&7None of your accessible vaults currently have stored items."));
            return;
        }

        $player->sendMessage(C::colorize("&r&bVaults with stored items: &f#" . implode("&7, &f#", $nonEmpty)));
    }

    private function listOtherVaults(Player $viewer, string $targetName) : void
    {
        if (!$viewer->hasPermission("glacia.admin")) {
            $viewer->sendMessage(C::colorize("&r&cYou do not have permission to view other players' vaults."));
            return;
        }

        $target = PlayerUtils::getPlayerByPrefix($targetName);
        $session = $target !== null ? Loader::getPlayerManager()->getSession($target) : Loader::getPlayerVaultManager()->resolvePlayerSession($targetName);
        if ($session === null) {
            $viewer->sendMessage(C::colorize("&r&cThat player could not be found."));
            return;
        }

        $nonEmpty = Loader::getPlayerVaultManager()->getNonEmptyVaultNumbers($session->getUuid()->toString());
        if ($nonEmpty === []) {
            $viewer->sendMessage(C::colorize("&r&7" . $session->getUsername() . " has no vaults with stored items."));
            return;
        }

        $viewer->sendMessage(C::colorize("&r&b" . $session->getUsername() . "'s vaults with stored items: &f#" . implode("&7, &f#", $nonEmpty)));
    }

    private function sendInstructions(Player $player) : void
    {
        $manager = Loader::getPlayerVaultManager();
        $vaults = $manager->getAccessibleVaults($player);
        if ($manager->hasInfiniteAccess($player)) {
            $vaultText = "&r&7Accessible vaults: &fAny number";
        } else {
            $vaultText = $vaults === []
                ? "&r&7Accessible vaults: &fNone"
                : "&r&7Accessible vaults: &f#" . implode("&7, &f#", array_slice($vaults, 0, 12));

            if (count($vaults) > 12) {
                $vaultText .= "&7...";
            }
        }

        $player->sendMessage(C::colorize("&r&eUse &f/pv <number> &r&eto open one of your vaults."));
        $player->sendMessage(C::colorize("&r&eUse &f/pv search <query> &r&eto search your accessible vaults."));
        $player->sendMessage(C::colorize("&r&eUse &f/pv list &r&eto see which vaults currently have items."));
        if ($player->hasPermission("glacia.admin")) {
            $player->sendMessage(C::colorize("&r&eUse &f/pv <number> <player> &r&eto inspect another player's vault."));
            $player->sendMessage(C::colorize("&r&eUse &f/pv list <player> &r&eto see which of their vaults have items."));
        }
        $player->sendMessage(C::colorize($vaultText));
    }

    public function getPermission() : string
    {
        return "glacia.default";
    }
}
