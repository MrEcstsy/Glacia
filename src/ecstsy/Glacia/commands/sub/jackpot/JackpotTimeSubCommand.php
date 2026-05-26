<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\jackpot;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class JackpotTimeSubCommand extends BaseSubCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("duration", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $seconds = GeneralUtils::parseTimeString((string) ($args["duration"] ?? ""));
        if ($seconds <= 0) {
            $sender->sendMessage(C::colorize("&r&cUsage: /jp time <duration>"));
            return;
        }

        $jackpot = Loader::getJackpotInstance();
        $jackpot->setTimeLeft($seconds);
        $sender->sendMessage(C::colorize("&r&aSet the jackpot timer to &f" . GeneralUtils::translateTime($seconds) . "&a."));
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
