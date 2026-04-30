<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\itemflip\ItemFlipManager;
use ecstsy\Glacia\utils\lang\MessageTemplates;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ItemFlipCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        $targetArg = trim((string) ($args["player"] ?? ""));

        if ($targetArg === "") {
            $this->sendHelp($sender);
            return;
        }

        $first = strtolower($targetArg);
        if (in_array($first, ["help", "?", "h"], true)) {
            $this->sendHelp($sender);
            return;
        }

        if (in_array($first, ["credits", "credit", "bal", "balance"], true)) {
            $sender->sendMessage(C::colorize(MessageTemplates::render("itemflip.credits", [
                "credits" => number_format((int) ($session?->getItemFlipCredits() ?? 0)),
            ])));
            return;
        }

        if (in_array($first, ["pending", "requests"], true)) {
            $rows = ItemFlipManager::getPendingFor($sender);
            if ($rows === []) {
                $sender->sendMessage(C::colorize(MessageTemplates::render("itemflip.pending.none")));
                return;
            }
            $sender->sendMessage(C::colorize(MessageTemplates::render("itemflip.pending.header")));
            foreach ($rows as $row) {
                $sender->sendMessage(C::colorize(MessageTemplates::render("itemflip.pending.entry", [
                    "player" => (string) $row["player"],
                    "credits" => (string) $row["credits"],
                    "expires" => (string) $row["expires_in"],
                ])));
            }
            return;
        }

        $target = PlayerUtils::getPlayerByPrefix($targetArg);
        if (!$target instanceof Player || !$target->isOnline()) {
            $sender->sendMessage(C::colorize(MessageTemplates::render("itemflip.error.player_offline")));
            return;
        }
        if ($target->getUniqueId()->toString() === $sender->getUniqueId()->toString()) {
            $sender->sendMessage(C::colorize(MessageTemplates::render("itemflip.error.self")));
            return;
        }

        if (ItemFlipManager::hasPendingFrom($sender, $target)) {
            ItemFlipManager::acceptChallenge($sender, $target);
            return;
        }

        ItemFlipManager::sendChallenge($sender, $target);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }

    private function sendHelp(Player $player): void
    {
        $player->sendMessage(C::colorize(MessageTemplates::render("itemflip.help.title")));
        $player->sendMessage(C::colorize(MessageTemplates::render("itemflip.help.challenge")));
        $player->sendMessage(C::colorize(MessageTemplates::render("itemflip.help.pending")));
        $player->sendMessage(C::colorize(MessageTemplates::render("itemflip.help.credits")));
        $player->sendMessage(C::colorize(MessageTemplates::render("itemflip.help.rules")));
    }
}

