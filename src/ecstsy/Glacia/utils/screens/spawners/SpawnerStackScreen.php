<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\spawners;

use ecstsy\Glacia\blocks\tiles\SpawnerTile;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use pocketmine\player\Player;
use Vecnavium\FormsUI\SimpleForm;
use pocketmine\utils\TextFormat as C;

final class SpawnerStackScreen extends BaseScreen {

    private SimpleForm $form;
    private SpawnerTile $tile;

    public function __construct(Player $player, SpawnerTile $tile) {
        $this->tile = $tile;

        $this->form = new SimpleForm(function (Player $player, ?int $data) : void {
            if ($data === null) return;

            switch ($data) {
                case 0: 
                    $added = $this->tryAddFromInventory($player, 1);
                    if ($added > 0) {
                        $player->sendMessage(C::colorize("&aAdded {$added} spawner(s) to the stack."));
                    } else {
                        $player->sendMessage(C::colorize("&cNo matching spawner in inventory or stack full."));
                    }
                    break;

                case 1: 
                    $removed = $this->tryRemoveToPlayer($player, 1);
                    if ($removed > 0) {
                        $player->sendMessage(C::colorize("&aRemoved {$removed} spawner(s) from the stack."));
                    } else {
                        $player->sendMessage(C::colorize("&cCould not remove spawner (stack may be at minimum)."));
                    }
                    break;

                case 2: 
                    $toAdd = $this->tile->getMaxStack() - $this->tile->getStackCount();
                    if ($toAdd <= 0) {
                        $player->sendMessage(C::colorize("&cStack already at max."));
                        break;
                    }
                    $added = $this->tryAddFromInventory($player, $toAdd);
                    $player->sendMessage($added > 0 ? C::colorize("&aAdded {$added} spawner(s).") : C::colorize("&cNo matching spawners or already full."));
                    break;

                case 3: 
                    $current = $this->tile->getStackCount();
                    $removable = max(0, $current - 1);
                    if ($removable <= 0) {
                        $player->sendMessage(C::colorize("&cNo spare spawners to take."));
                        break;
                    }
                    $removed = $this->tryRemoveToPlayer($player, $removable);
                    $player->sendMessage($removed > 0 ? C::colorize("&aRemoved {$removed} spawner(s).") : C::colorize("&cNothing removed."));
                    break;

                case 4: 
                default:
                    break;
            }
        });

        $entity = $tile->getEntityTypeId();
        $stack = $tile->getStackCount();
        $max = $tile->getMaxStack();
        $delay = $tile->getDelay();
        $baseCount = $tile->getStackCount();

        $parts = explode(":", $entity);
        $mob = $parts[1]; 

        $this->form->setTitle(C::colorize("&r&l&8" . ucfirst($mob) . " Spawner"));
        $content = "";
        $content .= C::colorize("&r&fStack: &7{$stack} &f/ &7{$max}\n");
        $content .= C::colorize("&r&fDelay: &7{$delay} ticks\n");
        $content .= C::colorize("&r&fBase spawn count: &7{$baseCount}");
        $this->form->setContent($content);

        $this->form->addButton(C::colorize("&r&aAdd 1"));
        $this->form->addButton(C::colorize("&r&cRemove 1"));
        $this->form->addButton(C::colorize("&r&aFill to Max"));
        $this->form->addButton(C::colorize("&r&cTake All (spare)"));
        $this->form->addButton(C::colorize("&r&8Cancel"));
    }

    public function getForm(): SimpleForm {
        return $this->form;
    }

    public static function displayForm(Player $player, SpawnerTile $tile): void {
        $screen = new self($player, $tile);
        $player->sendForm($screen->getForm());
    }

    /**
     * Try adding up to $count spawners by consuming matching item(s) from player's inventory.
     * Returns number of spawners actually added.
     */
    private function tryAddFromInventory(Player $player, int $count): int {
        if (!method_exists($this->tile, "addToStackFromPlayerInventory")) {
            return 0;
        }
        return (int) $this->tile->addToStackFromPlayerInventory($player, $count);
    }

    /**
     * Try removing up to $count spawners and give them to player (or drop if inventory full).
     * Returns number actually removed.
     */
    private function tryRemoveToPlayer(Player $player, int $count): int {
        if (!method_exists($this->tile, "removeFromStackToPlayer")) {
            return 0;
        }
        return (int) $this->tile->removeFromStackToPlayer($player, $count);
    }
}
