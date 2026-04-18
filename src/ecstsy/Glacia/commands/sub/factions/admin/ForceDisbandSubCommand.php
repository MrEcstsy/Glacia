<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions\admin;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\sound\ExplodeSound;

final class ForceDisbandSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("faction", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $factionName = $args["faction"] ?? null;

        if ($factionName === null) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_exists", ["name" => $factionName]));
            return;
        }

        $factionManager = FactionManager::getInstance();
        $faction = $factionManager->getFactionByName($factionName);

        if ($faction === null) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_exists", ["name" => $factionName]));
            return;
        }

        $factionId = $faction->getFactionId();

        foreach (Loader::getPlayerManager()->getSessions() as $session) {
            if ($session->getFactionId() === $factionId) {
                $member = $session->getPlayer();
                if ($member instanceof Player) {
                    $member->sendMessage(C::colorize("&r&l&c(X) &r&cFaction '&4{$faction->getName()}&c' has been disbanded by an Admin."));
                    $member->getWorld()->addSound($member->getPosition(), new ExplodeSound());
                    $member->getWorld()->addParticle($member->getEyePos(), new HugeExplodeParticle());
                }
            }
        }

        $factionManager->disbandFaction($factionId);

        $sender->sendMessage(C::colorize("&r&c&l(X) &r&cFaction '&b{$faction->getName()}&c' has been disbanded by &b{$sender->getName()}&c."));

        GlaciaUtils::logAdminAction("Force Disband", [
            "admin" => $sender->getName(),
            "faction" => $faction->getName(),
            "world" => $sender->getWorld()->getFolderName()
        ]);
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}
