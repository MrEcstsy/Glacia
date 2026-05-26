<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\jackpot\JackpotAddTimeSubCommand;
use ecstsy\Glacia\commands\sub\jackpot\JackpotBuySubCommand;
use ecstsy\Glacia\commands\sub\jackpot\JackpotDrawSubCommand;
use ecstsy\Glacia\commands\sub\jackpot\JackpotRemoveTimeSubCommand;
use ecstsy\Glacia\commands\sub\jackpot\JackpotTimeSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class JackpotCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerSubCommand(new JackpotBuySubCommand(Loader::getInstance(), "buy", "Buy jackpot tickets"));
        $this->registerSubCommand(new JackpotTimeSubCommand(Loader::getInstance(), "time", "Set the jackpot timer"));
        $this->registerSubCommand(new JackpotAddTimeSubCommand(Loader::getInstance(), "addtime", "Add time to the jackpot timer"));
        $this->registerSubCommand(new JackpotRemoveTimeSubCommand(Loader::getInstance(), "removetime", "Remove time from the jackpot timer", ["subtime"]));
        $this->registerSubCommand(new JackpotDrawSubCommand(Loader::getInstance(), "draw", "Force the jackpot to draw now"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $jackpot = Loader::getJackpotInstance();

        $pot = $jackpot->getPot();
        $tax = $jackpot->getTaxPercent();
        $tickets = $jackpot->getTickets();
        $totalTickets = array_sum($tickets);
        $yourTickets = $tickets[$sender->getUniqueId()->toString()] ?? 0;
        $winPercentage = $totalTickets > 0 ? round(($yourTickets / $totalTickets) * 100, 2) : 0;
        $time = $jackpot->getTimeLeft();
        $taxPercent = (int)($tax * 100);

        $sender->sendMessage(C::colorize("&r&b&lGlacia &fJackpot&r\n" .
            "  &bJackpot Value: &f$" . number_format($pot) . " &7($taxPercent% tax)\n" .
            "  &bTickets Sold: &f$totalTickets\n" .
            "  &bYour Tickets: &f$yourTickets &7(" . ($totalTickets > 0 ? "$winPercentage%" : "0%") . " chance)\n" .
            "&b(!) &fNext Winner in &b" . GeneralUtils::translateTime($time)
        ));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
