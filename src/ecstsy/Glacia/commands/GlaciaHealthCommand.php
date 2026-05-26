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

        $tps = $server->getTicksPerSecond();
        $tpsColor = $this->getTpsColor($tps);
        $runtime = $this->getServerRuntime();
        $memoryUsage = $this->getMemoryUsage();
        $totalEntities = $this->countTotalEntities($server);

        $sender->sendMessage(C::colorize("&r&b&lGlacia Health"));
        $sender->sendMessage(C::colorize(
            "&r&7Players: &f{$onlinePlayers} &8| &7Worlds: &f{$loadedWorlds}&7/&f{$configuredWorlds} configured &8| &7Vault editors: &f" . $vaultManager->getOpenEditorCount()
        ));
        
        if ($sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize(
                "&r&7TPS: {$tpsColor}&f{$tps}&r &8| &7Runtime: &f{$runtime} &8| &7Memory: &f{$memoryUsage}"
            ));
        }
        
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
        
        if ($sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&7--- World Entity Breakdown ---"));
            foreach ($server->getWorldManager()->getWorlds() as $world) {
                $entityCount = count($world->getEntities());
                $entityCountColor = $this->getEntityCountColor($entityCount);
                $sender->sendMessage(C::colorize(
                    "&r&7{$world->getFolderName()}: {$entityCountColor}&f{$entityCount}&r entities"
                ));
            }
        }
    }

    private function formatToggle(bool $enabled): string
    {
        return $enabled ? "&aon" : "&coff";
    }

    /**
     * Color TPS value based on server health
     * Green (20 TPS) = healthy | Yellow (15-19) = mild lag | Red (<15) = severe lag
     */
    private function getTpsColor(float $tps): string
    {
        if ($tps >= 19.5) {
            return "&a";
        } elseif ($tps >= 15.0) {
            return "&e";
        }
        return "&c";
    }

    /**
     * Get entity count color based on load
     * Green (<200) = normal | Yellow (200-500) = moderate | Red (>500) = heavy load
     */
    private function getEntityCountColor(int $count): string
    {
        if ($count < 200) {
            return "&a";
        } elseif ($count < 500) {
            return "&e";
        }
        return "&c";
    }

    /**
     * Calculate server runtime since last start
     * Returns formatted string like "2h 34m"
     */
    private function getServerRuntime(): string
    {
        // PocketMine tracks ticks since startup
        // Get current tick count and divide by 20 (ticks per second) to get seconds
        $uptime = (int)ceil(Loader::getInstance()->getServer()->getTick() / 20);
        
        $hours = (int)($uptime / 3600);
        $minutes = (int)(($uptime % 3600) / 60);
        $secs = $uptime % 60;
        
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        return "{$minutes}m {$secs}s";
    }

    /**
     * Get memory usage as formatted string
     * Shows current memory usage / maximum allocated
     */
    private function getMemoryUsage(): string
    {
        $current = memory_get_usage(true) / 1024 / 1024;
        $peak = memory_get_peak_usage(true) / 1024 / 1024;
        $limit = (int)ini_get('memory_limit');
        
        return sprintf("%.0fMB / %dMB", $current, $limit);
    }

    /**
     * Count total entities across all loaded worlds
     */
    private function countTotalEntities($server): int
    {
        $total = 0;
        foreach ($server->getWorldManager()->getWorlds() as $world) {
            $total += count($world->getEntities());
        }
        return $total;
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
