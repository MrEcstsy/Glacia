<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PlaytimeTopVisibilityCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("target_or_state", true));
        $this->registerArgument(1, new RawStringArgument("state", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        [$targetName, $stateArg] = $this->resolveArgs(
            isset($args["target_or_state"]) ? (string) $args["target_or_state"] : "",
            isset($args["state"]) ? (string) $args["state"] : ""
        );

        $targetSession = null;
        $targetLabel = $sender->getName();

        if ($targetName === null) {
            $targetSession = Loader::getPlayerManager()->getSession($sender);
        } else {
            $targetPlayer = PlayerUtils::getPlayerByPrefix($targetName);
            if ($targetPlayer instanceof Player) {
                $targetSession = Loader::getPlayerManager()->getSession($targetPlayer);
                $targetLabel = $targetPlayer->getName();
            }

            if (!$targetSession instanceof GlaciaPlayer) {
                $targetSession = Loader::getPlayerManager()->getSessionByPrefix($targetName);
                $targetLabel = $targetSession?->getUsername() ?? $targetLabel;
            }
        }

        if (!$targetSession instanceof GlaciaPlayer) {
            $sender->sendMessage(C::colorize("&r&cNo player profile matches &f{$targetName}&c."));
            return;
        }

        $current = $targetSession->isHiddenFromPlaytimeTop();
        $next = $this->resolveDesiredState($stateArg, $current);
        if ($next === null) {
            $sender->sendMessage(C::colorize("&r&cUse &f/playtimehide [player] <on|off|toggle>&c."));
            return;
        }

        $targetSession->setHiddenFromPlaytimeTop($next);

        if ($next) {
            $sender->sendMessage(C::colorize("&r&a{$targetLabel} is now hidden from playtime top."));
        } else {
            $sender->sendMessage(C::colorize("&r&a{$targetLabel} will now appear on playtime top."));
        }
    }

    public function getPermission(): string
    {
        return "glacia.staff";
    }

    /**
     * @return array{0:?string,1:string}
     */
    private function resolveArgs(string $first, string $second): array
    {
        $first = trim($first);
        $second = trim($second);

        if ($first === "") {
            return [null, "toggle"];
        }

        if ($this->isStateKeyword($first)) {
            return [null, $first];
        }

        return [$first, $second !== "" ? $second : "toggle"];
    }

    private function isStateKeyword(string $value): bool
    {
        return in_array(strtolower(trim($value)), ["on", "off", "show", "hide", "true", "false", "toggle"], true);
    }

    private function resolveDesiredState(string $value, bool $current): ?bool
    {
        return match (strtolower(trim($value))) {
            "", "toggle" => !$current,
            "on", "hide", "true" => true,
            "off", "show", "false" => false,
            default => null,
        };
    }
}
