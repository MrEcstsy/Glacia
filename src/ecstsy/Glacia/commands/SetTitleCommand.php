<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\titles\TitleManager;
use ecstsy\Glacia\utils\screens\titles\TitleSelectorScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SetTitleCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("title", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null) {
            return;
        }

        $title = strtolower(trim((string) ($args["title"] ?? "")));
        if ($title === "") {
            TitleSelectorScreen::display($sender);
            return;
        }

        if ($title === "clear") {
            $session->removeTitle();
            $sender->sendMessage(C::colorize("&r&8Title cleared."));
            return;
        }

        if (!TitleManager::exists($title)) {
            $sender->sendMessage(C::colorize("&r&cUnknown title id. Use &f/title &r&cto open the menu."));
            PlayerUtils::playSound($sender, "note.bass");
            return;
        }

        $data = TitleManager::get($title);
        if ($data === null || !TitleManager::hasUnlocked($session, $title)) {
            $sender->sendMessage(C::colorize("&r&cThat title is locked. Claim a voucher first."));
            PlayerUtils::playSound($sender, "note.bass");
            return;
        }

        $session->setTitle(C::colorize((string) $data["format"]));
        $sender->sendMessage(C::colorize("&r&aTitle set to &r" . (string) $data["format"]));
        PlayerUtils::playSound($sender, "random.levelup");
    }

    public function getPermission(): ?string
    {
        return "glacia.command.setTitle";
    }
}