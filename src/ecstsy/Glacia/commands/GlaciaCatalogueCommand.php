<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\screens\items\GlaciaCatalogueScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GlaciaCatalogueCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("category", true));
        $this->registerArgument(1, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cOpen the Glacia item catalogue in-game."));
            return;
        }

        $categoryLookup = strtolower(trim((string) ($args["category"] ?? "")));
        if ($categoryLookup === "" || in_array($categoryLookup, ["menu", "categories", "browse"], true)) {
            GlaciaCatalogueScreen::openCategoryMenu($sender);
            return;
        }

        $category = GlaciaCatalogueScreen::resolveCategory($categoryLookup, $sender);
        if ($category === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown catalogue category &f{$categoryLookup}&c. Try &f/gci&c to browse."));
            return;
        }

        $page = max(1, (int) ($args["page"] ?? 1));
        (new GlaciaCatalogueScreen($sender, $category, $page))->display($sender);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
