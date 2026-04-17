<?php
declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions\admin;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\AdminLogger;
use ecstsy\Glacia\utils\DiscordLogger;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SetPowerSubCommand extends BaseSubCommand {
    public function prepare(): void {
        $this->setPermission("glacia.admin");
        $this->registerArgument(0, new RawStringArgument("player", true));
        $this->registerArgument(1, new IntegerArgument("amount", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $name = $args["player"] ?? "";
        $amount = (int)($args["amount"] ?? 0);
        if ($amount < 0) {
            $sender->sendMessage(C::colorize("&r&l&cError: &r&4Amount must be greater than 0."));
            return;
        }

        $targetPlayer = PlayerUtils::getPlayerByPrefix($name);
        if ($targetPlayer !== null) {
            $session = Loader::getPlayerManager()->getSession($targetPlayer);
            if ($session !== null) {
                $session->setPower($amount);
                $sender->sendMessage(C::colorize("&r&l&a(✓) &r&2You have set " . $targetPlayer->getName() . "'s power to &a" . $amount));
                $logMsg = GlaciaUtils::formatAdminLog("Set Power", [
                    "admin" => $sender->getName(),
                    "target" => $targetPlayer->getName(),
                    "amount" => $amount
                ]);

                DiscordLogger::send($logMsg);
                return;
            }
        }
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}
