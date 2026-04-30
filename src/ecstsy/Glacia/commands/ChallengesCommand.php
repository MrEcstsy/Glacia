<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\screens\challenges\ChallengesScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ChallengesCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $manager = Loader::getChallengeManager();
        if (!$manager->isEnabled()) {
            $sender->sendMessage(C::colorize("&r&cDaily challenges are currently disabled."));
            return;
        }

        $action = strtolower((string) ($args["action"] ?? "open"));
        if ($action === "claim") {
            $claimed = $manager->claimPendingRewards($sender);
            if ($claimed <= 0) {
                $sender->sendMessage(C::colorize("&r&7You have no pending challenge rewards."));
                return;
            }
            $sender->sendMessage(C::colorize("&r&aClaimed &f" . number_format($claimed) . " &achallenge rewards."));
            return;
        }

        ChallengesScreen::display($sender);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
