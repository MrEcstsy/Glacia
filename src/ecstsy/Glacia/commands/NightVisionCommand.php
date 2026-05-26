<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class NightVisionCommand extends BaseCommand
{
    private const PERMANENT_DURATION_TICKS = 20 * 60 * 60 * 24 * 365;

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("first", true));
        $this->registerArgument(1, new RawStringArgument("second", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $first = isset($args["first"]) ? strtolower(trim((string) $args["first"])) : "";
        $second = isset($args["second"]) ? strtolower(trim((string) $args["second"])) : "";

        [$target, $action] = $this->resolveTargetAndAction($sender, $args["first"] ?? "", $args["second"] ?? "");
        if ($target === null) {
            $sender->sendMessage(C::colorize('&r&cUsage: /nv [on|off|toggle|status] [player]'));
            return;
        }

        if ($target !== $sender && !$sender->hasPermission('glacia.nv.other')) {
            $sender->sendMessage(C::colorize('&r&cYou cannot manage night vision for other players.'));
            return;
        }

        $session = PlayerManager::getInstance()->ensureSessionLoaded($target);
        $currentlyEnabled = (bool) $session->getSetting("night_vision");
        $enabled = match ($action) {
            "on" => true,
            "off" => false,
            "status" => $currentlyEnabled,
            default => !$currentlyEnabled,
        };

        if ($action !== "status") {
            $session->setSetting("night_vision", $enabled);
            $this->syncNightVisionState($target, $enabled);
        } else {
            $this->syncNightVisionState($target, $currentlyEnabled);
        }

        $statusText = $enabled ? '&aenabled' : '&cdisabled';
        if ($action === "status") {
            $target->sendMessage(C::colorize('&r&7Night vision is currently ' . $statusText . '&r&7.'));
            if ($sender !== $target) {
                $sender->sendMessage(C::colorize('&r&7Night vision for &f' . $target->getName() . '&7 is ' . $statusText . '&r&7.'));
            }
            return;
        }

        $target->sendMessage(C::colorize('&r&7Night vision ' . ($enabled ? '&aenabled' : '&cdisabled') . '&r&7.'));
        if ($sender !== $target) {
            $sender->sendMessage(C::colorize('&r&7Night vision ' . ($enabled ? '&aenabled' : '&cdisabled') . '&r&7 for &f' . $target->getName() . '&7.'));
        }
    }

    public function getPermission(): string
    {
        return 'glacia.nv';
    }

    private function syncNightVisionState(Player $player, bool $enabled): void
    {
        if ($enabled) {
            $this->applyPermanentNightVision($player);
            return;
        }

        $player->getEffects()->remove(VanillaEffects::NIGHT_VISION());
    }

    /**
     * @return array{0:?Player,1:string}
     */
    private function resolveTargetAndAction(CommandSender $sender, mixed $firstArg, mixed $secondArg): array
    {
        $first = trim((string) $firstArg);
        $second = trim((string) $secondArg);
        $firstLower = strtolower($first);
        $secondLower = strtolower($second);
        $actions = ["on", "off", "toggle", "status"];

        if (in_array($firstLower, $actions, true)) {
            $target = $second !== "" ? PlayerUtils::getPlayerByPrefix($second) : ($sender instanceof Player ? $sender : null);
            return [$target, $firstLower];
        }

        if ($first === "") {
            return [$sender instanceof Player ? $sender : null, "toggle"];
        }

        $target = PlayerUtils::getPlayerByPrefix($first);
        if ($target !== null) {
            $action = in_array($secondLower, $actions, true) ? $secondLower : "toggle";
            return [$target, $action];
        }

        if ($sender instanceof Player) {
            return [$sender, $firstLower];
        }

        return [null, "toggle"];
    }

    private function applyPermanentNightVision(Player $player): void
    {
        $player->getEffects()->add(new EffectInstance(
            VanillaEffects::NIGHT_VISION(),
            self::PERMANENT_DURATION_TICKS,
            0,
            false,
            false,
            null,
            false
        ));
    }
}
