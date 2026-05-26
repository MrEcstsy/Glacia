<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\jackpot;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class JackpotRemoveTimeSubCommand extends BaseSubCommand
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
            $sender->sendMessage(C::colorize("&r&cUsage: /jp removetime <duration>"));
            return;
        }

        $jackpot = Loader::getJackpotInstance();
        $jackpot->removeTime($seconds);
        $sender->sendMessage(C::colorize("&r&aRemoved &f" . GeneralUtils::translateTime($seconds) . " &afrom the jackpot timer. &7(New: &f" . GeneralUtils::translateTime($jackpot->getTimeLeft()) . "&7)"));
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
