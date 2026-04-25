<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\enchanter;

use ecstsy\Glacia\server\enchanter\EnchanterRepository;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class EnchanterFormScreen {

    public static function display(Player $player): void
    {
        $books = array_values(EnchanterRepository::getBooks());

        $form = new SimpleForm(function(Player $player, mixed $data) use ($books): void {
            if (!is_string($data) || !ctype_digit($data) || !isset($books[(int) $data])) {
                return;
            }

            EnchanterConfirmScreen::displayForm($player, $books[(int) $data]);
        });

        $form->setTitle(C::colorize("&r&8Glacia Enchanter"));
        $form->setContent(C::colorize("&r&7Choose an enchantment book tier."));

        foreach ($books as $index => $bookData) {
            $group = ucfirst((string) $bookData["group"]);
            $price = number_format((int) $bookData["price"]);
            $form->addButton(C::colorize("&r&8{$group} Book\n&r&b{$price} XP"), label: (string) $index);
        }

        $player->sendForm($form);
    }
}
