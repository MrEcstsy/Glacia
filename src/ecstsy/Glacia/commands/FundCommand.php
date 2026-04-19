<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FundCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("value", true));
        $this->registerArgument(1, new RawStringArgument("extra", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $manager = Loader::getGlobalFundManager();
        $value = strtolower((string) ($args["value"] ?? ""));
        $extra = (string) ($args["extra"] ?? "");

        if ($value === "") {
            $this->sendInfo($sender);
            return;
        }

        if ($sender->hasPermission("glacia.admin")) {
            if ($value === "force" || $value === "start") {
                $started = $extra !== ""
                    ? $manager->forceStartById(strtolower($extra))
                    : $manager->forceStart();
                $sender->sendMessage(C::colorize($started
                    ? "&r&aForced a new global fund event."
                    : ($extra !== "" ? "&r&cCould not start that fund. Check the id or end the current one first." : "&r&cA global fund is already active.")
                ));
                return;
            }

            if ($value === "end") {
                $sender->sendMessage(C::colorize($manager->forceFail()
                    ? "&r&aEnded the active global fund."
                    : "&r&cThere is no active global fund."
                ));
                return;
            }

            if ($value === "complete") {
                $sender->sendMessage(C::colorize($manager->forceComplete()
                    ? "&r&aCompleted the active global fund."
                    : "&r&cThere is no active global fund."
                ));
                return;
            }

            if ($value === "time") {
                $seconds = GeneralUtils::parseTimeString($extra);
                if ($seconds <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /fund time <duration>"));
                    return;
                }

                $sender->sendMessage(C::colorize($manager->setActiveTimeLeft($seconds)
                    ? "&r&aSet the active fund timer to &f" . GeneralUtils::translateTime($seconds) . "&a."
                    : "&r&cThere is no active global fund."
                ));
                return;
            }

            if ($value === "addtime") {
                if ($extra === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /fund addtime <duration|-duration>"));
                    return;
                }

                $negative = str_starts_with($extra, "-");
                $seconds = GeneralUtils::parseTimeString(ltrim($extra, "+-"));
                if ($seconds <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /fund addtime <duration|-duration>"));
                    return;
                }

                $delta = $negative ? -$seconds : $seconds;
                $sender->sendMessage(C::colorize($manager->addActiveTime($delta)
                    ? "&r&aAdjusted the active fund timer by &f" . ($negative ? "-" : "+") . GeneralUtils::translateTime($seconds) . "&a."
                    : "&r&cThere is no active global fund."
                ));
                return;
            }

            if ($value === "next") {
                $seconds = GeneralUtils::parseTimeString($extra);
                if ($seconds <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /fund next <duration>"));
                    return;
                }

                $manager->setNextStartDelay($seconds);
                $sender->sendMessage(C::colorize("&r&aSet the next fund to begin in &f" . GeneralUtils::translateTime($seconds) . "&a."));
                return;
            }

            if ($value === "goal") {
                $amount = (int) GeneralUtils::parseRandomNumber($extra);
                if ($amount <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /fund goal <amount>"));
                    return;
                }

                $sender->sendMessage(C::colorize($manager->setGoal($amount)
                    ? "&r&aSet the active fund goal to &f$" . number_format($amount) . "&a."
                    : "&r&cThere is no active global fund."
                ));
                return;
            }

            if ($value === "progress") {
                $amount = (int) GeneralUtils::parseRandomNumber($extra);
                if ($amount < 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /fund progress <amount>"));
                    return;
                }

                $sender->sendMessage(C::colorize($manager->setContribution($amount)
                    ? "&r&aSet the active fund progress to &f$" . number_format($amount) . "&a."
                    : "&r&cThere is no active global fund."
                ));
                return;
            }

            if ($value === "list") {
                $ids = $manager->getEventIds();
                $sender->sendMessage(C::colorize("&r&bAvailable Fund IDs&7: " . ($ids === [] ? "&cNone" : "&f" . implode("&7, &f", $ids))));
                return;
            }
        }

        $amount = (int) GeneralUtils::parseRandomNumber($value);
        if ($amount <= 0) {
            $sender->sendMessage(C::colorize("&r&6Fund Commands"));
            $sender->sendMessage(C::colorize("&r&e/fund &7View the active fund."));
            $sender->sendMessage(C::colorize("&r&e/fund <amount> &7Contribute money to the event."));
            if ($sender->hasPermission("glacia.admin")) {
                $sender->sendMessage(C::colorize("&r&e/fund start [id]&7, &e/fund end&7, &e/fund complete"));
                $sender->sendMessage(C::colorize("&r&e/fund time <duration>&7, &e/fund addtime <duration|-duration>"));
                $sender->sendMessage(C::colorize("&r&e/fund next <duration>&7, &e/fund goal <amount>&7, &e/fund progress <amount>"));
                $sender->sendMessage(C::colorize("&r&e/fund list &7View all configured event ids."));
            }
            return;
        }

        $response = $manager->contribute($sender, $amount);
        if ($response !== "") {
            $sender->sendMessage($response);
        }
    }

    private function sendInfo(Player $player): void
    {
        $manager = Loader::getGlobalFundManager();
        $active = $manager->getFundInfo();

        if ($active === null) {
            $player->sendMessage(C::colorize("&r&l&bGlobal Fund"));
            $player->sendMessage(C::colorize("&r&7No event is active right now."));
            $player->sendMessage(C::colorize("&r&7Next fund starts in &f" . GeneralUtils::translateTime($manager->getTimeLeft()) . "&7."));
            return;
        }

        $goal = (int) ($active["goal"] ?? 0);
        $contributed = (int) ($active["contributed"] ?? 0);
        $display = (string) ($active["display_name"] ?? "Global Fund");

        $player->sendMessage(C::colorize("&r&l&b{$display}"));
        $player->sendMessage(C::colorize("&r&7Progress: &a$" . number_format($contributed) . " &8/ &f$" . number_format($goal)));
        $player->sendMessage(C::colorize("&r&7Time Left: &f" . GeneralUtils::translateTime($manager->getTimeLeft())));

        foreach ((array) ($active["description"] ?? []) as $line) {
            $player->sendMessage(C::colorize((string) $line));
        }
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
