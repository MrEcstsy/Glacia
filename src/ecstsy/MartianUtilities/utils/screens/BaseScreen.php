<?php

declare(strict_types=1);

namespace ecstsy\MartianUtilities\utils\screens;

use ecstsy\MartianUtilities\interfaces\ScreenInterface;
use muqsit\invmenu\InvMenu;
use Vecnavium\FormsUI\CustomForm;
use Vecnavium\FormsUI\SimpleForm;

abstract class BaseScreen implements ScreenInterface {

    public function getMenu(): InvMenu|null
    {
        return null;
    }

    public function getForm(): CustomForm|null|SimpleForm
    {
        return null;
    }
}