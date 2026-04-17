<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use pocketmine\utils\TextFormat as C;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\ranks\RankManager;
use pocketmine\command\CommandSender;

final class CreateRankSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("key", false));
        $this->registerArgument(1, new RawStringArgument("displayName", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $key = $args["key"];
        $display= $args["displayName"];
        $rankManager = RankManager::getInstance();

        if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $key)) {
            $sender->sendMessage(C::colorize("&r&aRank key must be 3–16 alphanumeric characters (letters and numbers only)."));
            return;
        }

        if ($rankManager->getRank($key) !== null) {
            $sender->sendMessage(C::colorize("&r&cRank '$key' already exists."));
            return;
        }

        if (mb_strlen($display) > 16) {
            $sender->sendMessage(C::colorize("&r&aRank display name must be 16 characters or less."));
            return;
        }

        $defaultformat = "&r{fac_relation}{fac_rank}{fac_name} &7{name}&f: &7{message}";

        RankManager::getInstance()->createRank($key, $display, null, $defaultformat);
        $sender->sendMessage(C::colorize("&r&aRank '$key' created."));
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}