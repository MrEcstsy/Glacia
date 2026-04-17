<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class SetNametagFormatSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("rank"));
        $this->registerArgument(1, new TextArgument("format"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $key = strtolower(trim((string) $args["rank"]));
        $format = (string) $args["format"];
        $rank = RankManager::getInstance()->getRank($key);

        if ($rank === null) {
            $sender->sendMessage(C::colorize("&r&cRank '$key' not found."));
            return;
        }

        if (strpos($format, "{name}") === false) {
            $sender->sendMessage(C::colorize("&r&cNametag format must contain {name}."));
            return;
        }

        $rank->setNametagFormat($format);
        foreach (Loader::getPlayerManager()->getSessions() as $session) {
            $player = $session->getPlayer();
            if ($player !== null) {
                GlaciaUtils::refreshNametagsForTarget($player);
            }
        }

        $sender->sendMessage(C::colorize("&r&aNametag format for rank '$key' set."));

        GlaciaUtils::logAdminAction("Set Rank Nametag", [
            "admin" => $sender->getName(),
            "target" => $key,
            "format" => $format
        ]);
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
