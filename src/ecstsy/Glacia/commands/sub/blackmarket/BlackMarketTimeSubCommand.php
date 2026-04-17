<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\blackmarket;

use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class BlackMarketTimeSubCommand extends BaseSubCommand {

    protected function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("action"));
        $this->registerArgument(1, new IntegerArgument("seconds"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $bah = Loader::getBlackMarketAuctionInstance();

        $action = strtolower((string) ($args["action"] ?? ""));
        $seconds = (int) ($args["seconds"] ?? 0);

        if ($seconds <= 0) {
            $sender->sendMessage(C::colorize("&r&cTime must be greater than 0 seconds."));
            return;
        }

        switch ($action) {
            case "add":
                if ($bah->hasStarted()) {
                    $bah->setEndTime($bah->getEndTime() + $seconds);

                    $sender->sendMessage(C::colorize(
                        "&r&aAdded &f" . $seconds . "s&a to the active auction. New remaining time: &f" . $bah->getAuctionTimeLeft() . "s"
                    ));
                    return;
                }

                $bah->setNextAuctionTime($bah->getNextAuctionTime() + $seconds);

                $sender->sendMessage(C::colorize(
                    "&r&aAdded &f" . $seconds . "s&a to the next auction countdown. New remaining time: &f" . $bah->getRemaining() . "s"
                ));
                return;

            case "remove":
            case "sub":
                if ($bah->hasStarted()) {
                    $bah->setEndTime(max(time(), $bah->getEndTime() - $seconds));

                    $sender->sendMessage(C::colorize(
                        "&r&aRemoved &f" . $seconds . "s&a from the active auction. New remaining time: &f" . $bah->getAuctionTimeLeft() . "s"
                    ));
                    return;
                }

                $bah->setNextAuctionTime(max(time(), $bah->getNextAuctionTime() - $seconds));

                $sender->sendMessage(C::colorize(
                    "&r&aRemoved &f" . $seconds . "s&a from the next auction countdown. New remaining time: &f" . $bah->getRemaining() . "s"
                ));
                return;

            case "set":
                if ($bah->hasStarted()) {
                    $bah->setEndTime(time() + $seconds);

                    $sender->sendMessage(C::colorize(
                        "&r&aSet active auction remaining time to &f" . $seconds . "s&a."
                    ));
                    return;
                }

                $bah->setNextAuctionTime(time() + $seconds);

                $sender->sendMessage(C::colorize(
                    "&r&aSet next auction countdown to &f" . $seconds . "s&a."
                ));
                return;
        }

        $sender->sendMessage(C::colorize("&r&cUsage: /bah time <add|remove|set> <seconds>"));
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}