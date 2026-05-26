<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\customies;

use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

final class EtherealCrateIconItem extends Item implements ItemComponents
{
    use ItemComponentsTrait;

    public function __construct(ItemIdentifier $identifier, string $name = "Ethereal Crate")
    {
        parent::__construct($identifier, $name);
        $this->initComponent("ethereal_crate_icon");
    }

    public function getMaxStackSize(): int
    {
        return 64;
    }
}
