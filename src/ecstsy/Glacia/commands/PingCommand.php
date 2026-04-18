<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PingCommand extends BaseCommand {

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

        $target = $sender;
        $targetName = $args["player"] ?? null;
        if (is_string($targetName) && $targetName !== "") {
            $target = PlayerUtils::getPlayerByPrefix($targetName) ?? $sender;
            if ($target === $sender && strcasecmp($sender->getName(), $targetName) !== 0) {
                $sender->sendMessage(C::colorize("&r&cThat player could not be found."));
                return;
            }
        }

        $ping = $target->getNetworkSession()->getPing();
        $color = GlaciaUtils::getPingColor($ping);
        $bars = GlaciaUtils::getNetworkBar($ping);

        $sender->sendMessage(C::colorize("&r&b" . $target->getName() . "'s Ping&7: {$color}{$ping}ms &7{$bars}"));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
