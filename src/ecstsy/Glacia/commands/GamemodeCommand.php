<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GamemodeCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        
        $this->registerArgument(0, new RawStringArgument("mode", true));
        $this->registerArgument(1, new PlayerArgument("player", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $modeName = strtolower($aliasUsed);

        $shortcuts = [
            "gms" => GameMode::SURVIVAL(),
            "gmc" => GameMode::CREATIVE(),
            "gma" => GameMode::ADVENTURE(),
            "gmsp" => GameMode::SPECTATOR(),
        ];

        if (isset($shortcuts[$modeName])) {
            $gamemode = $shortcuts[$modeName];
        } else {
            $raw = $args["mode"] ?? "";
            switch (strtolower($raw)) {
                case "s":
                case "survival":
                    $gamemode = GameMode::SURVIVAL();
                    break;
                case "c":
                case "creative":
                    $gamemode = GameMode::CREATIVE();
                    break;
                case "a":
                case "adventure":
                    $gamemode = GameMode::ADVENTURE();
                    break;
                case "sp":
                case "spectator":
                    $gamemode = GameMode::SPECTATOR();
                    break;
                default:
                    $sender->sendMessage(C::colorize("&eUsage(s):"));
                    $sender->sendMessage(C::colorize("&f/gm &6<survival|creative|adventure|spectator> [player]"));
                    return;
            }
        }

        /** @var Player|null $target */
        $target = $args["player"] ?? null;

        if ($target === null && isset($shortcuts[$modeName])) {
            $lookup = trim((string) ($args["mode"] ?? ""));
            if ($lookup !== "") {
                $target = PlayerUtils::getPlayerByPrefix($lookup);
                if ($target === null) {
                    $sender->sendMessage(C::colorize("&r&cThat player is not online."));
                    return;
                }
            }
        }

        if ($target === null) {
            if (!$sender instanceof Player) {
                $sender->sendMessage(C::colorize("&r&cConsole usage: /{$aliasUsed} <player>"));
                return;
            }
            $target = $sender;
        }

        $target->setGamemode($gamemode);
        $msg = C::colorize(Loader::SERVER_PREFIX . "&fSet game mode &d" . $gamemode->getEnglishName() . " &ffor &d" . $target->getNameTag());
        $sender->sendMessage($msg);
        if ($sender instanceof Player) {
            PlayerUtils::playSound($sender, "random.levelup");
        }
    }

    public function getPermission(): string {
        return "glacia.gamemode";
    }
}
