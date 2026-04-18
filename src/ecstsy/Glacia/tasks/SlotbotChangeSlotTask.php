<?php

namespace ecstsy\Glacia\tasks;

use pocketmine\inventory\Inventory;
use pocketmine\scheduler\Task;
use pocketmine\utils\Random;

class SlotbotChangeSlotTask extends Task
{
    /** @var array */
    private $items;

    /** @var Inventory */
    private $inventory;

    public function __construct(array $items, Inventory $inventory)
    {
        $this->items = $items;
        $this->inventory = $inventory;
    }

    public function onRun(): void
    {
        $random = new Random();
        $randomItem = $this->items[$random->nextBoundedInt(count($this->items))];
        $this->inventory->setItem(13, $randomItem);
    }
}
