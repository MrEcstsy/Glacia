<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\ranks\RankManager;
use pocketmine\utils\TextFormat as C;
use pocketmine\command\CommandSender;

final class SetChatFormatSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("rank"));
        $this->registerArgument(1, new TextArgument("format"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $key = $args["rank"];
        $format = $args["format"];
        $rankManager = RankManager::getInstance();

        $rank = $rankManager->getRank($key);

        if ($rank === null) {
            $sender->sendMessage(C::colorize("&r&cRank '$key' not found."));
            return;
        }

        if (strpos($format, "{message}") === false || strpos($format, "{name}") === false) {
            $sender->sendMessage(C::colorize("&r&cFormat must contain {message} and {name}."));
            return;
        }

        $rank->setChatFormat($format);

        $sender->sendMessage(C::colorize("&r&aChat format for rank '$key' set."));
    }   

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}