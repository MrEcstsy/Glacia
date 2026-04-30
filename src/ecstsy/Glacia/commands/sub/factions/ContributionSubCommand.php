<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\contribution\FactionContributionManager;
use ecstsy\Glacia\utils\screens\faction\FactionContributionScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ContributionSubCommand extends BaseSubCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new IntegerArgument("amount", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }
        if (!FactionContributionManager::isEnabled()) {
            $sender->sendMessage(C::colorize("&r&cFaction contribution is currently disabled."));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        $faction = $session?->getFaction();
        if ($faction === null) {
            $sender->sendMessage(C::colorize("&r&cYou are not in a faction."));
            return;
        }

        $action = strtolower((string) ($args["action"] ?? ""));
        if ($action === "" || $action === "info") {
            FactionContributionScreen::display($sender);
            return;
        }

        if ($action !== "deposit") {
            $sender->sendMessage(C::colorize("&r&cUsage: /f contribute [info|deposit [amount]]"));
            return;
        }

        $requested = isset($args["amount"]) ? max(1, (int) $args["amount"]) : null;
        FactionContributionScreen::depositFromHand($sender, $requested);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}

