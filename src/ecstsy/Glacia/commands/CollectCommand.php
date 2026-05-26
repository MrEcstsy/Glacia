<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\utils\screens\CollectScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class CollectCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new TextArgument("input", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $input = trim((string) ($args["input"] ?? ""));
        if ($input === "") {
            CollectScreen::display($sender);
            return;
        }

        if (!$sender->hasPermission("glacia.admin")) {
            CollectScreen::display($sender);
            return;
        }

        $parts = preg_split('/\s+/', $input) ?: [];
        $action = strtolower((string) array_shift($parts));
        $targetName = trim(implode(" ", $parts));

        if (!in_array($action, ["add", "remove"], true) || $targetName === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /collect <add|remove> <player>"));
            return;
        }

        $targetSession = $this->resolveTargetSession($targetName);
        if ($targetSession === null) {
            $sender->sendMessage(C::colorize("&r&cThat player could not be found."));
            return;
        }

        $heldItem = clone $sender->getInventory()->getItemInHand();
        if ($heldItem->isNull() || $heldItem->getCount() <= 0) {
            $sender->sendMessage(C::colorize("&r&cHold the item you want to {$action} to their /collect."));
            return;
        }

        if ($action === "add") {
            $targetSession->addItemToCollection($heldItem);
            $sender->sendMessage(C::colorize("&r&aAdded &f" . $heldItem->getName() . " x" . $heldItem->getCount() . " &ato &f" . $targetSession->getUsername() . "&a's /collect."));
            return;
        }

        if ($targetSession->removeItemFromCollection($heldItem)) {
            $sender->sendMessage(C::colorize("&r&aRemoved &f" . $heldItem->getName() . " x" . $heldItem->getCount() . " &afrom &f" . $targetSession->getUsername() . "&a's /collect."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&cThat exact item was not found in &f" . $targetSession->getUsername() . "&c's /collect."));
    }

    private function resolveTargetSession(string $targetName): ?GlaciaPlayer
    {
        $online = PlayerUtils::getPlayerByPrefix($targetName);
        if ($online instanceof Player) {
            return Loader::getPlayerManager()->getSession($online);
        }

        return Loader::getPlayerManager()->getSessionByPrefix($targetName);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
