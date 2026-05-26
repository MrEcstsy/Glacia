<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\FloatArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\entity\InteractiveCrystalEntity;
use ecstsy\Glacia\entity\InteractiveNpcEntity;
use ecstsy\Glacia\entity\InteractiveSpawnEntity;
use ecstsy\Glacia\server\spawn\InteractiveSpawnAction;
use ecstsy\Glacia\server\spawn\InteractiveSpawnPlacement;
use ecstsy\Glacia\server\spawn\InteractiveSpawnRemoveMode;
use ecstsy\Glacia\server\spawn\InteractiveSpawnSelection;
use ecstsy\Glacia\server\spawn\NpcSkinRepository;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use function strtolower;
use function trim;

final class SpawnSlapperCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("mode", true));
        $this->registerArgument(1, new RawStringArgument("type", true));
        $this->registerArgument(2, new RawStringArgument("action", true));
        $this->registerArgument(3, new RawStringArgument("skin", true));
        $this->registerArgument(4, new FloatArgument("yoffset", true));
        $this->registerArgument(5, new TextArgument("label", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        $mode = strtolower(trim((string) ($args["mode"] ?? "help")));
        if ($mode === "remove" || $mode === "delete") {
            InteractiveSpawnRemoveMode::enable($sender);
            $sender->sendMessage(C::colorize("&r&aRemove mode enabled. &7Hit or right-click a slapper/crystal to delete it (&f2 minutes&7)."));
            return;
        }

        if ($mode === "move" || $mode === "relocate") {
            $this->handleMove($sender, $args);
            return;
        }

        if ($mode === "y" || $mode === "entityy") {
            $this->handleAdjustY($sender, $args, false);
            return;
        }

        if ($mode === "holo" || $mode === "texty" || $mode === "text") {
            $this->handleAdjustY($sender, $args, true);
            return;
        }

        if ($mode !== "spawn" && $mode !== "create") {
            $this->sendHelp($sender);
            return;
        }

        $type = strtolower(trim((string) ($args["type"] ?? "")));
        $action = trim((string) ($args["action"] ?? ""));
        $skinArg = trim((string) ($args["skin"] ?? ""));
        $labelArg = trim((string) ($args["label"] ?? ""));
        $isCrystal = in_array($type, ["crystal", "endcrystal", "slotbotcrystal"], true);
        $yOffset = (float) ($args["yoffset"] ?? 0.0);
        if (abs($yOffset) < 0.001 && $labelArg === "" && is_numeric($skinArg)) {
            $yOffset = (float) $skinArg;
            $skinArg = "";
        }

        [$skinName, $label] = $this->resolveSkinAndLabel($type, $skinArg, $labelArg);
        if ($type === "" || $action === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /spawnslapper spawn <npc|crystal> <action> [skin] [y] [label]"));
            return;
        }

        if ($label === "") {
            $label = InteractiveSpawnAction::suggestLabel($action);
        }

        InteractiveSpawnRemoveMode::disable($sender);
        $location = InteractiveSpawnPlacement::atPlayerFeet($sender, $isCrystal, $yOffset);
        $entity = match ($type) {
            "npc", "player", "slapper" => new InteractiveNpcEntity($location, $this->resolveNpcSkin($skinName, $action)),
            "crystal", "endcrystal", "slotbotcrystal" => new InteractiveCrystalEntity($location),
            default => null,
        };

        if (!$entity instanceof Entity || !$entity instanceof InteractiveSpawnEntity) {
            $sender->sendMessage(C::colorize("&r&cUnknown type. Use &fnpc &cor &fcrystal&c."));
            return;
        }

        $entity->setInteractAction($action);
        $entity->spawnToAll();
        $entity->setDisplayLabel($label);

        if ($entity instanceof Human) {
            $entity->sendSkin();
        }

        $skinNote = "";
        if ($type === "npc" || $type === "player" || $type === "slapper") {
            $skinNote = " &7skin: &f" . NpcSkinRepository::getLastResolvedSource();
        }
        $yNote = abs($yOffset) > 0.001 ? C::colorize(" &7at Y offset &f" . $yOffset) : "";
        $sender->sendMessage(C::colorize("&r&aSpawned a &f{$type}&a with action &f{$action}&a{$skinNote}{$yNote}&a."));
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }

    private function sendHelp(Player $sender): void
    {
        $sender->sendMessage(C::colorize("&r&cUsage: /spawnslapper spawn <npc|crystal> <action> [skin] [y] [label]"));
        $sender->sendMessage(C::colorize("&r&7Adjust nearest slapper (&fwithin 7 blocks&7):"));
        $sender->sendMessage(C::colorize("&r&7  &f/spawnslapper move [y] &7- move entity to your feet"));
        $sender->sendMessage(C::colorize("&r&7  &f/spawnslapper y <delta> &7- raise/lower the &fentity"));
        $sender->sendMessage(C::colorize("&r&7  &f/spawnslapper holo <delta> &7- raise/lower the &fhologram text"));
        $sender->sendMessage(C::colorize("&r&7  &f/spawnslapper y <entity> <holo> &7- adjust both at once"));
        $sender->sendMessage(C::colorize("&r&7Actions: &fshop, blackmarket, slotbot, enchanter, quest, questshop, duel, freefixall"));
            $sender->sendMessage(C::colorize("&r&7NPC skins: &fplugins/Glacia/skins/shop.png &7(64x64 skin file)"));
            $sender->sendMessage(C::colorize("&r&7Or copy an online player: &fspawn npc shop player:TheirName"));
        $sender->sendMessage(C::colorize("&r&7Remove: &f/spawnslapper remove &7then hit or right-click"));
    }

    /**
     * @param array<string, mixed> $args
     */
    private function handleMove(Player $sender, array $args): void
    {
        $entity = InteractiveSpawnSelection::findNearest($sender);
        if ($entity === null) {
            $sender->sendMessage(C::colorize("&r&cNo nearby slapper or crystal found."));
            return;
        }

        $yOffset = (float) ($args["type"] ?? $args["yoffset"] ?? 0.0);
        $entity->relocateAnchorTo($sender, $yOffset);
        $sender->sendMessage(C::colorize("&r&aMoved the nearest slapper to your position."));
    }

    /**
     * @param array<string, mixed> $args
     */
    private function handleAdjustY(Player $sender, array $args, bool $holoOnly): void
    {
        $entity = InteractiveSpawnSelection::findNearest($sender);
        if ($entity === null) {
            $sender->sendMessage(C::colorize("&r&cNo nearby slapper or crystal found."));
            return;
        }

        $primary = (float) ($args["type"] ?? 0.0);
        $secondary = (float) ($args["action"] ?? 0.0);

        if ($holoOnly) {
            $entity->shiftHologramYOffset($primary);
            $sender->sendMessage(C::colorize("&r&aHologram Y adjusted by &f{$primary}&a (&7now &f" . $entity->getHologramYOffset() . "&7)."));
            return;
        }

        if (abs($primary) > 0.001) {
            $entity->shiftAnchorY($primary);
        }
        if (abs($secondary) > 0.001) {
            $entity->shiftHologramYOffset($secondary);
        }

        if (abs($primary) < 0.001 && abs($secondary) < 0.001) {
            $sender->sendMessage(C::colorize("&r&cUsage: /spawnslapper y <entityDelta> [holoDelta]"));
            return;
        }

        $sender->sendMessage(C::colorize("&r&aAdjusted nearest slapper. &7Entity &f" . ($primary >= 0 ? "+" : "") . "{$primary}&7, hologram &f" . ($secondary >= 0 ? "+" : "") . "{$secondary}"));
    }

    /**
     * @return array{0:?string,1:string}
     */
    private function resolveSkinAndLabel(string $type, string $skinArg, string $labelArg): array
    {
        if (!in_array($type, ["npc", "player", "slapper"], true)) {
            return [null, trim(($skinArg . " " . $labelArg))];
        }

        if ($skinArg === "") {
            return [null, $labelArg];
        }

        if ($labelArg !== "") {
            return [$skinArg, $labelArg];
        }

        if (str_starts_with(strtolower($skinArg), "player:")) {
            return [$skinArg, $labelArg];
        }

        if (NpcSkinRepository::hasSkin($skinArg)) {
            return [$skinArg, $labelArg];
        }

        return [null, $labelArg !== "" ? $labelArg : $skinArg];
    }

    private function resolveNpcSkin(?string $skinName, string $action): \pocketmine\entity\Skin
    {
        return NpcSkinRepository::resolveSkin($skinName, $action);
    }
}
