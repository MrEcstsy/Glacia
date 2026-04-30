<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\titles;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\titles\TitleManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class TitleSelectorScreen{

    private const PER_PAGE = 10;

    public static function display(Player $player, int $page = 0): void{
        $session = Loader::getPlayerManager()->getSession($player);
        if($session === null){
            return;
        }

        $titles = TitleManager::getTitles();
        if($titles === []){
            $player->sendMessage(C::colorize("&r&cNo titles are configured."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $entries = [];
        foreach($titles as $id => $data){
            $entries[] = ["id" => $id, "data" => $data];
        }

        $maxPage = max(0, (int) ceil(count($entries) / self::PER_PAGE) - 1);
        $page = max(0, min($maxPage, $page));
        $slice = array_slice($entries, $page * self::PER_PAGE, self::PER_PAGE);

        $form = new SimpleForm(function(Player $player, mixed $data) use ($page, $maxPage, $session, $entries): void{
            if($data === null || $data === "close"){
                return;
            }

            if($data === "prev"){
                self::display($player, $page - 1);
                return;
            }

            if($data === "next"){
                self::display($player, $page + 1);
                return;
            }

            if($data === "clear"){
                $session->removeTitle();
                $player->sendMessage(C::colorize("&r&8Your title has been cleared."));
                PlayerUtils::playSound($player, "random.click");
                return;
            }

            if(!str_starts_with((string) $data, "title:")){
                return;
            }

            $titleId = substr((string) $data, 6);
            $title = TitleManager::get($titleId);
            if($title === null){
                $player->sendMessage(C::colorize("&r&cThat title is no longer available."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if(!TitleManager::hasUnlocked($session, $titleId)){
                $player->sendMessage(C::colorize("&r&8Status: &cLOCKED &8- claim a voucher first."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $session->setTitle(C::colorize((string) $title["format"]));
            $player->sendMessage(C::colorize("&r&8Title set to &r" . (string) $title["format"]));
            PlayerUtils::playSound($player, "random.levelup");
        });

        $form->setTitle(C::colorize("&r&8Titles"));
        $form->setContent(C::colorize("&r&8Select a title\n&8Page &f" . ($page + 1) . "&8/&f" . ($maxPage + 1)));

        foreach($slice as $entry){
            $id = (string) $entry["id"];
            $data = (array) $entry["data"];
            $display = (string) ($data["display"] ?? ucfirst($id));
            $status = TitleManager::hasUnlocked($session, $id) ? "&aUNLOCKED" : "&cLOCKED";
            $form->addButton(C::colorize("&r" . $display . "\n&8Status: " . $status), label: "title:" . $id);
        }

        if($page > 0){
            $form->addButton(C::colorize("&r&bPrevious"), label: "prev");
        }
        if($page < $maxPage){
            $form->addButton(C::colorize("&r&bNext"), label: "next");
        }
        $form->addButton(C::colorize("&r&8Clear Title"), label: "clear");
        $form->addButton(C::colorize("&r&7Close"), label: "close");
        $player->sendForm($form);
    }
}

