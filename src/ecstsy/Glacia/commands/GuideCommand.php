<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\guide\GuideManager;
use ecstsy\Glacia\utils\screens\guide\GuideScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GuideCommand extends BaseCommand {

    protected function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("category", true));
        $this->registerArgument(1, new RawStringArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cOpen the guide in-game (forms)."));
            return;
        }

        $rawCat = strtolower(trim((string) ($args["category"] ?? "")));
        if (in_array($rawCat, ["menu", "home", "hub"], true)) {
            GuideScreen::openMain($sender);
            return;
        }
        if ($rawCat === "reload") {
            if (!$sender->hasPermission("glacia.admin")) {
                $sender->sendMessage(C::colorize("&r&cYou cannot reload the guide."));
                return;
            }
            GuideManager::load();
            $sender->sendMessage(C::colorize("&r&aGuide configuration reloaded."));
            return;
        }

        $pageRaw = trim((string) ($args["page"] ?? ""));

        if ($rawCat === "") {
            GuideScreen::openMain($sender);
            return;
        }

        $categoryId = GuideManager::resolveCategory($rawCat);
        if ($categoryId === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown guide section &f" . $rawCat . "&c. Try &f/guide &cwith no args."));
            return;
        }

        if ($pageRaw === "") {
            GuideScreen::openCategory($sender, $categoryId);
            return;
        }

        $pageId = GuideManager::resolvePage($categoryId, $pageRaw);
        if ($pageId === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown topic &f" . $pageRaw . "&c in that section."));
            GuideScreen::openCategory($sender, $categoryId);
            return;
        }

        GuideScreen::openPage($sender, $categoryId, $pageId);
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
