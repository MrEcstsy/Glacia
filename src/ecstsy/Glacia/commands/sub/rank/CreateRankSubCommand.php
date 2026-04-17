<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class CreateRankSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("key"));
        $this->registerArgument(1, new RawStringArgument("displayName"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $key = strtolower(trim((string) $args["key"]));
        $display = trim((string) $args["displayName"]);
        $rankManager = RankManager::getInstance();

        if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $key)) {
            $sender->sendMessage(C::colorize("&r&cRank key must be 3-16 alphanumeric characters or underscores."));
            return;
        }

        if ($rankManager->getRank($key) !== null) {
            $sender->sendMessage(C::colorize("&r&cRank '$key' already exists."));
            return;
        }

        if ($display === "" || mb_strlen($display) > 16) {
            $sender->sendMessage(C::colorize("&r&cRank display name must be between 1 and 16 characters."));
            return;
        }

        $defaultChatFormat = "&r{fac_relation}{fac_rank}{fac_name} &7{name}&f: &7{message}";
        $defaultNametagFormat = "&r&7<{$display}&7>\n&r{fac_relation}{fac_rank}{fac_name} &7{name}";

        $rankManager->createRank($key, $display, null, $defaultChatFormat, $defaultNametagFormat);
        $sender->sendMessage(C::colorize("&r&aRank '$key' created."));

        GlaciaUtils::logAdminAction("Create Rank", [
            "admin" => $sender->getName(),
            "target" => $key,
            "display" => $display
        ]);
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
