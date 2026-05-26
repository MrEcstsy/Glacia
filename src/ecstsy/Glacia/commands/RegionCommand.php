<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class RegionCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("name", true));
        $this->registerArgument(2, new RawStringArgument("preset", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        $manager = Loader::getRegionManager();
        $action = strtolower((string) ($args["action"] ?? ""));

        switch ($action) {
            case "pos1":
            case "1":
                $manager->setSelectionPoint($sender, $sender->getPosition(), "pos1");
                $pos = $sender->getPosition();
                $sender->sendMessage(C::colorize("&r&aSet region pos1 to &f" . $pos->getWorld()->getFolderName() . " {$pos->getFloorX()}, {$pos->getFloorY()}, {$pos->getFloorZ()}&a."));
                return;

            case "pos2":
            case "2":
                $manager->setSelectionPoint($sender, $sender->getPosition(), "pos2");
                $pos = $sender->getPosition();
                $sender->sendMessage(C::colorize("&r&aSet region pos2 to &f" . $pos->getWorld()->getFolderName() . " {$pos->getFloorX()}, {$pos->getFloorY()}, {$pos->getFloorZ()}&a."));
                return;

            case "save":
            case "create":
                $name = trim((string) ($args["name"] ?? ""));
                $preset = strtolower(trim((string) ($args["preset"] ?? "")));
                if ($name === "" || $preset === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: &f/region save <name> <spawn|warzone|safezone>"));
                    return;
                }
                if (!$manager->isValidPreset($preset)) {
                    $sender->sendMessage(C::colorize("&r&cUnknown preset. Use: &f" . implode(", ", $manager->getPresetNames())));
                    return;
                }

                $selection = $manager->getSelection($sender);
                if (!isset($selection["pos1"], $selection["pos2"])) {
                    $sender->sendMessage(C::colorize("&r&cSet both points first with &f/region pos1 &cand &f/region pos2&c."));
                    return;
                }

                $region = $manager->createOrUpdateRegionFromSelection($sender, $name, $preset);
                if ($region === null) {
                    $sender->sendMessage(C::colorize("&r&cCouldn't save that region. Make sure both points are in the same world."));
                    return;
                }

                $sender->sendMessage(C::colorize("&r&aSaved region &f" . $region->getName() . "&a as &f{$preset}&a protection."));
                $sender->sendMessage(C::colorize("&r&7Use &f/region info " . strtolower($region->getName()) . " &7to verify bounds."));
                if ($preset === "warzone" && strtolower($region->getName()) !== "warzone") {
                    $sender->sendMessage(C::colorize("&r&eFor built-in warzone behavior, name this region &fwarzone&e."));
                }
                if ($preset === "spawn" && !in_array(strtolower($region->getName()), ["spawn", "safezone", "safe_zone"], true)) {
                    $sender->sendMessage(C::colorize("&r&eFor built-in spawn/safezone behavior, use a name like &fspawn &eor &fsafezone&e."));
                }
                return;

            case "info":
            case "status":
                $name = trim((string) ($args["name"] ?? ""));
                if ($name === "") {
                    $selection = $manager->getSelection($sender);
                    $sender->sendMessage(C::colorize("&r&bRegion selection"));
                    if (($selection["pos1"] ?? null) instanceof \pocketmine\world\Position) {
                        $pos1 = $selection["pos1"];
                        $sender->sendMessage(C::colorize("&r&7Pos1: &f" . $pos1->getWorld()->getFolderName() . " {$pos1->getFloorX()}, {$pos1->getFloorY()}, {$pos1->getFloorZ()}"));
                    } else {
                        $sender->sendMessage(C::colorize("&r&7Pos1: &cnot set"));
                    }
                    if (($selection["pos2"] ?? null) instanceof \pocketmine\world\Position) {
                        $pos2 = $selection["pos2"];
                        $sender->sendMessage(C::colorize("&r&7Pos2: &f" . $pos2->getWorld()->getFolderName() . " {$pos2->getFloorX()}, {$pos2->getFloorY()}, {$pos2->getFloorZ()}"));
                    } else {
                        $sender->sendMessage(C::colorize("&r&7Pos2: &cnot set"));
                    }
                    $sender->sendMessage(C::colorize("&r&7Save with: &f/region save <name> <spawn|warzone|safezone>"));
                    return;
                }

                $region = $manager->getRegionByName($name);
                if ($region === null) {
                    $sender->sendMessage(C::colorize("&r&cUnknown region &f{$name}&c."));
                    return;
                }

                $min = $region->getMin();
                $max = $region->getMax();
                $permissions = $region->permissions();
                $sender->sendMessage(C::colorize("&r&bRegion &f" . $region->getName()));
                $sender->sendMessage(C::colorize("&r&7World: &f" . $region->getWorld()));
                $sender->sendMessage(C::colorize("&r&7Min: &f{$min->getFloorX()}, {$min->getFloorY()}, {$min->getFloorZ()}"));
                $sender->sendMessage(C::colorize("&r&7Max: &f{$max->getFloorX()}, {$max->getFloorY()}, {$max->getFloorZ()}"));
                $sender->sendMessage(C::colorize("&r&7PvP: &f" . ($permissions->pvp ? "on" : "off") . " &8| &7Build: &f" . ($permissions->build ? "on" : "off") . " &8| &7Explosions: &f" . ($permissions->explosions ? "on" : "off")));
                return;

            case "delete":
            case "remove":
                $name = trim((string) ($args["name"] ?? ""));
                if ($name === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: &f/region delete <name>"));
                    return;
                }
                if (!$manager->deleteRegion($name)) {
                    $sender->sendMessage(C::colorize("&r&cCouldn't delete region &f{$name}&c."));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&aDeleted region &f{$name}&a."));
                return;

            case "list":
                $regions = $manager->getRegions();
                if ($regions === []) {
                    $sender->sendMessage(C::colorize("&r&cNo regions are configured."));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&bRegions: &f" . implode(", ", array_map(static fn($region) => $region->getName(), $regions))));
                return;

            case "clear":
            case "reset":
                $manager->clearSelection($sender);
                $sender->sendMessage(C::colorize("&r&eCleared your current region selection."));
                return;
        }

        $sender->sendMessage(C::colorize("&r&cUsage: &f/region <pos1|pos2|save|info|list|delete|clear>"));
        $sender->sendMessage(C::colorize("&r&7Example: &f/region pos1 &7-> &f/region pos2 &7-> &f/region save warzone warzone"));
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
