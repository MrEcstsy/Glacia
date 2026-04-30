<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\exp;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\world\sound\XpCollectSound;
use pocketmine\utils\TextFormat as C;

final class AddExpSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new PlayerArgument("player", false));
        $this->registerArgument(1, new RawStringArgument("amount", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        /** @var Player|null $player */
        $player = $args["player"];
        $amount = GlaciaUtils::parseShorthandNumber((string) ($args["amount"] ?? ""));

        if ($player === null || !$player instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cError: &4Player not found."));
            return;
        }

        if ($amount <= 0) {
            $sender->sendMessage(C::colorize("&r&cError: &4Amount must be greater than 0."));
            return;
        }

        $player->getXpManager()->addXp($amount);
        
        if ($sender instanceof Player) {
            $sender->getWorld()->addSound($sender->getPosition(), new XpCollectSound());
        }
        $sender->sendMessage(C::colorize(Loader::SERVER_PREFIX . $player->getName() . " &fnow has &d" . number_format($player->getXpManager()->getCurrentTotalXp()) . " &fexp."));
    }

    public function getPermission(): string {
        return "glacia.add-xp";
    }
}
