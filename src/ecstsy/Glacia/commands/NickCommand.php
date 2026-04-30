<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class NickCommand extends BaseCommand
{
    private const MAX_LENGTH = 16;

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("nickname", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null) {
            return;
        }

        $input = trim((string) ($args["nickname"] ?? ""));
        if ($input === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /nick <name|off>"));
            return;
        }

        if (in_array(strtolower($input), ["off", "reset", "clear"], true)) {
            $session->setSetting("nickname", null);
            GlaciaUtils::refreshNametagsForTarget($sender);
            $sender->sendMessage(C::colorize("&r&aNickname removed."));
            return;
        }

        $clean = preg_replace('/[§&][0-9a-fk-or]/i', '', $input) ?? "";
        $clean = preg_replace('/[^A-Za-z0-9_]/', '', $clean) ?? "";
        $clean = trim($clean);

        if ($clean === "") {
            $sender->sendMessage(C::colorize("&r&cNickname must contain letters/numbers only."));
            return;
        }

        if (strlen($clean) > self::MAX_LENGTH) {
            $sender->sendMessage(C::colorize("&r&cNickname can be at most " . self::MAX_LENGTH . " characters."));
            return;
        }

        $session->setSetting("nickname", $clean);
        GlaciaUtils::refreshNametagsForTarget($sender);
        $sender->sendMessage(C::colorize("&r&aNickname set to &f{$clean}&a."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
