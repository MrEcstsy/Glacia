<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\inventory;

use ecstsy\Glacia\Loader;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;

final class CustomSizedInvMenu {

    public static function create(int $size) : InvMenu {
		static $ids_by_size = [];
		if(!isset($ids_by_size[$size])){
			$id = Loader::TYPE_DYNAMIC_PREFIX . $size;
			InvMenuHandler::getTypeRegistry()->register($id, CustomSizedInvMenuType::ofSize($size));
			$ids_by_size[$size] = $id;
		}
		return InvMenu::create($ids_by_size[$size]);
	}
}