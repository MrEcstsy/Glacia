<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class GlaciaHealthCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $server = Loader::getInstance()->getServer();
        $worldManager = Loader::getWorldManagerService();
        $vaultManager = Loader::getPlayerVaultManager();
        $fundManager = Loader::getGlobalFundManager();
        $envoyManager = Loader::getEnvoyManager();
        $voteManager = Loader::getVoteManager();
        $tebexManager = Loader::getTebexManager();
        $blackMarket = Loader::getBlackMarketAuctionInstance();
        $capture = Loader::getCaptureManager();

        $onlinePlayers = count($server->getOnlinePlayers());
        $loadedWorlds = count($server->getWorldManager()->getWorlds());
        $configuredWorlds = $worldManager->getConfiguredWorldCount();
        $activeFund = $fundManager->getFundInfo();
        $activeEnvoy = $envoyManager->getActive();
        $activeKoth = $capture->getActiveKothId();

        $sender->sendMessage(C::colorize("&r&b&lGlacia Health"));
        $sender->sendMessage(C::colorize(
            "&r&7Players: &f{$onlinePlayers} &8| &7Worlds: &f{$loadedWorlds}&7/&f{$configuredWorlds} configured &8| &7Vault editors: &f" . $vaultManager->getOpenEditorCount()
        ));
        $sender->sendMessage(C::colorize(
            "&r&7Vote: " . $this->formatToggle($voteManager->isEnabled())
            . " &8(" . count($voteManager->getVoteSites()) . " sites)"
            . " &8| &7Tebex: " . $this->formatToggle($tebexManager->isEnabled())
            . " &8(" . $tebexManager->getPollSeconds() . "s poll)"
        ));
        $sender->sendMessage(C::colorize(
            "&r&7Fund: &f" . ($activeFund !== null ? "active" : "idle")
            . " &8(" . GeneralUtils::translateTime($fundManager->getTimeLeft()) . ")"
            . " &8| &7Envoy: &f" . ($activeEnvoy !== null ? "active" : "idle")
            . " &8(" . GeneralUtils::translateTime($envoyManager->getTimeLeft()) . ")"
        ));
        $sender->sendMessage(C::colorize(
            "&r&7Black Market: &f" . ($blackMarket->hasActiveAuction() ? "active" : ($blackMarket->isCountingDown() ? "countdown" : "idle"))
            . " &8(" . GeneralUtils::translateTime($blackMarket->getRemaining()) . ")"
            . " &8| &7KOTH: &f" . ($activeKoth !== null ? $activeKoth : "none")
        ));
        $sender->sendMessage(C::colorize(
            "&r&7Challenges: " . $this->formatToggle(Loader::getChallengeManager()->isEnabled())
            . " &8| &7Death Logs: " . $this->formatToggle(Loader::getDeathInventoryLogManager()->isEnabled())
            . " &8| &7Grace: " . $this->formatToggle(Loader::getGraceInstance()->isEnabled())
        ));
    }

    private function formatToggle(bool $enabled): string
    {
        return $enabled ? "&aon" : "&coff";
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
