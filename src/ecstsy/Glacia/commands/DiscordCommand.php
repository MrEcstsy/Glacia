<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\discord\DiscordLinkManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class DiscordCommand extends BaseCommand{

    public function prepare(): void{
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("arg1", true));
        $this->registerArgument(2, new RawStringArgument("arg2", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void{
        $action = strtolower(trim((string) ($args["action"] ?? "status")));

        if ($sender instanceof ConsoleCommandSender) {
            $this->handleConsole($sender, $action, (string) ($args["arg1"] ?? ""), (string) ($args["arg2"] ?? ""));
            return;
        }

        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null) {
            return;
        }

        switch ($action) {
            case "link":
                $code = DiscordLinkManager::createLinkCode($sender);
                if ($code === "") {
                    $sender->sendMessage(C::colorize("&r&cUnable to create link code right now."));
                    return;
                }

                $sender->sendMessage(C::colorize("&r&8&m        &r &b&lDiscord link &r&8&m        "));
                $sender->sendMessage(C::colorize("&r&7In Discord, run: &f/link &7then paste your code."));
                $sender->sendMessage(C::colorize("&r&bCode: &f&l" . $code));
                $sender->sendMessage(C::colorize("&r&7Expires in &f10 minutes&7."));
                $sender->sendMessage(C::colorize("&r&8&m                        "));
                return;

            case "unlink":
                if (!DiscordLinkManager::unlinkSession($session)) {
                    $sender->sendMessage(C::colorize("&r&8&m        &r &c&lDiscord &r&8&m        "));
                    $sender->sendMessage(C::colorize("&r&7Nothing to unlink."));
                    $sender->sendMessage(C::colorize("&r&8&m                        "));
                    return;
                }

                $sender->sendMessage(C::colorize("&r&8&m        &r &a&lDiscord &r&8&m        "));
                $sender->sendMessage(C::colorize("&r&aUnlinked &7your Discord from this account."));
                $sender->sendMessage(C::colorize("&r&8&m                        "));
                return;

            case "status":
            default:
                $payload = DiscordLinkManager::getStatusPayload($session);
                if (!((bool) ($payload["linked"] ?? false))) {
                    $sender->sendMessage(C::colorize("&r&8&m        &r &b&lDiscord &r&8&m        "));
                    $sender->sendMessage(C::colorize("&r&7Status: &cNot linked"));
                    $sender->sendMessage(C::colorize("&r&7Use &f/discord link &7to get a code, then &f/link &7in Discord."));
                    $sender->sendMessage(C::colorize("&r&8&m                        "));
                    return;
                }

                $linkedAt = (int) ($payload["linked_at"] ?? 0);
                $ago = $linkedAt > 0 ? GeneralUtils::translateTime(max(1, time() - $linkedAt)) . " ago" : "—";
                $sender->sendMessage(C::colorize("&r&8&m        &r &b&lDiscord &r&8&m        "));
                $sender->sendMessage(C::colorize("&r&7Status: &aLinked"));
                $sender->sendMessage(C::colorize("&r&7Linked: &f" . $ago));
                $sender->sendMessage(C::colorize("&r&7Discord ID: &f" . (string) ($payload["discord_id"] ?? "—")));
                $sender->sendMessage(C::colorize("&r&8&m                        "));
                return;
        }
    }

    private function handleConsole(ConsoleCommandSender $sender, string $action, string $arg1, string $arg2): void{
        switch ($action) {
            case "claim":
                $result = DiscordLinkManager::claim($arg1, $arg2);
                $sender->sendMessage(json_encode($result, JSON_UNESCAPED_SLASHES));
                return;

            case "lookup":
                $session = Loader::getPlayerManager()->getSessionByPrefix($arg1);
                if ($session === null) {
                    $sender->sendMessage(json_encode(["ok" => false, "message" => "player_not_found"], JSON_UNESCAPED_SLASHES));
                    return;
                }

                $sender->sendMessage(json_encode(["ok" => true] + DiscordLinkManager::getStatusPayload($session), JSON_UNESCAPED_SLASHES));
                return;

            case "rank":
                $session = Loader::getPlayerManager()->getSessionByPrefix($arg1);
                if ($session === null) {
                    $sender->sendMessage("RANK:unknown");
                    return;
                }
                $sender->sendMessage("RANK:" . $session->getRank());
                return;

            case "unlinkdiscord":
                $session = DiscordLinkManager::unlinkByDiscordId($arg1);
                if ($session === null) {
                    $sender->sendMessage(json_encode(["ok" => false, "message" => "discord_not_linked"], JSON_UNESCAPED_SLASHES));
                    return;
                }
                $sender->sendMessage(json_encode(["ok" => true, "username" => $session->getUsername()], JSON_UNESCAPED_SLASHES));
                return;

            default:
                $sender->sendMessage(json_encode([
                    "ok" => false,
                    "message" => "usage: discordlink claim <discord_id> <code> | lookup <player> | rank <player> | unlinkdiscord <discord_id>"
                ], JSON_UNESCAPED_SLASHES));
                return;
        }
    }

    public function getPermission(): string{
        return "glacia.default";
    }
}

