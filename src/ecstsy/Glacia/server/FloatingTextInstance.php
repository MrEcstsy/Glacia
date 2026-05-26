<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server;

use ecstsy\Glacia\entity\FloatingTextEntity;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class FloatingTextInstance {

    /** 
     * Definition structure:
     *  - pos:    x,y,z,world
     *  - text:   array of lines (can contain {count})
     * @var array<string, array{pos: array, text: string[]}>
     */
    public static array $definitions  = [];

    /** @var array<string, FloatingTextEntity> */
    public static array $particles = [];

    public static function register(): void {
        self::removeAll();
        
        self::spawnAll();
    }

    public static function removeAll(): void {
        foreach(Server::getInstance()->getWorldManager()->getWorlds() as $w){
            foreach($w->getEntities() as $e){
                if($e instanceof FloatingTextEntity){
                    $e->flagForDespawn();
                }
            }
        }
        self::$particles = [];
    }

    public static function spawnAll(): void {
        $srv = Server::getInstance();
        foreach(self::$definitions as $id => $def){
            $world = $srv->getWorldManager()->getWorldByName($def["pos"]["world"]);
            if($world === null) continue;

            $count = count($srv->getOnlinePlayers());
            $text  = implode("\n", array_map(
                fn(string $line) => str_replace("{count}", (string)$count, $line),
                $def["text"]
            ));

            $nbt = self::makeDefaultNBT($def["pos"]);
            $loc = new Location(
                $def["pos"]["x"], $def["pos"]["y"], $def["pos"]["z"],
                $world, 0.0, 0.0
            );
            $ent = new FloatingTextEntity($loc, $nbt);
            $ent->setText(C::colorize($text));
            $ent->spawnToAll();
            self::$particles[$id] = $ent;
        }
    }

    public static function makeDefaultNBT(array $pos): CompoundTag {
        return CompoundTag::create()
            ->setTag("Pos", new ListTag([
                new DoubleTag($pos["x"]), new DoubleTag($pos["y"]), new DoubleTag($pos["z"])
            ]))
            ->setTag("Motion", new ListTag([
                new DoubleTag(0.0), new DoubleTag(0.0), new DoubleTag(0.0)
            ]))
            ->setTag("Rotation", new ListTag([
                new FloatTag(0.0), new FloatTag(0.0)
            ]));
    }

    /**
     * Always call after spawnAll, from your join-listener:
     *   FloatingTextsInstance::showToPlayer($player);
     */
    public static function showToPlayer(Player $player): void {
        foreach(self::$particles as $e){
            $e->spawnTo($player);
        }
    }
}