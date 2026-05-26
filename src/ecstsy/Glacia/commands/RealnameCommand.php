<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class RealnameCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $query = trim((string) ($args["player"] ?? ""));
        if ($query === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /realname <player|nickname>"));
            return;
        }

        $session = $this->findSession($query);
        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cNo player or nickname matched &f{$query}&c."));
            return;
        }

        $nickname = $this->getNickname($session);
        if ($nickname === null) {
            $sender->sendMessage(C::colorize("&r&f" . $session->getUsername() . " &7is not currently nicked."));
            return;
        }

        if ($this->matchesNickname($session, $query)) {
            $sender->sendMessage(C::colorize("&r&d{$nickname} &7belongs to &f" . $session->getUsername() . "&7."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&f" . $session->getUsername() . " &7is currently nicked as &d{$nickname}&7."));
    }

    public function getPermission(): string
    {
        return "glacia.command.realname";
    }

    private function findSession(string $query): ?GlaciaPlayer
    {
        $normalizedQuery = $this->normalize($query);
        $best = null;
        $bestDelta = PHP_INT_MAX;

        foreach (Loader::getPlayerManager()->getSessions() as $session) {
            $username = strtolower($session->getUsername());
            $nickname = $this->getNickname($session);
            $nicknameNormalized = $nickname !== null ? $this->normalize($nickname) : null;

            if ($username === $normalizedQuery || $nicknameNormalized === $normalizedQuery) {
                return $session;
            }

            foreach ([$username, $nicknameNormalized] as $candidate) {
                if ($candidate === null || !str_starts_with($candidate, $normalizedQuery)) {
                    continue;
                }

                $delta = strlen($candidate) - strlen($normalizedQuery);
                if ($delta < $bestDelta) {
                    $best = $session;
                    $bestDelta = $delta;
                }
            }
        }

        return $best;
    }

    private function matchesNickname(GlaciaPlayer $session, string $query): bool
    {
        $nickname = $this->getNickname($session);
        return $nickname !== null && $this->normalize($nickname) === $this->normalize($query);
    }

    private function getNickname(GlaciaPlayer $session): ?string
    {
        $nickname = $session->getSetting("nickname");
        if (!is_string($nickname)) {
            return null;
        }

        $nickname = trim($nickname);
        return $nickname !== "" ? $nickname : null;
    }

    private function normalize(string $value): string
    {
        return strtolower(ltrim(trim($value), "~"));
    }
}
