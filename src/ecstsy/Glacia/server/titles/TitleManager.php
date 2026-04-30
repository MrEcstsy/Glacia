<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\titles;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\utils\TextFormat as C;

final class TitleManager{

    /** @var array<string, array<string, mixed>> */
    private static array $titles = [];

    public static function loadFromConfig(): void{
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "titles.yml");
        $rawTitles = (array) ($config?->get("titles", []) ?? []);
        self::$titles = [];

        foreach($rawTitles as $id => $data){
            if(!is_array($data)){
                continue;
            }

            $titleId = self::normalizeId((string) $id);
            if($titleId === ""){
                continue;
            }

            $display = (string) ($data["display"] ?? ucfirst(str_replace("_", " ", $titleId)));
            $format = (string) ($data["format"] ?? "");
            if($format === ""){
                continue;
            }

            self::$titles[$titleId] = [
                "display" => $display,
                "format" => $format,
                "description" => (string) ($data["description"] ?? "&r&8Unlockable title."),
                "permission" => (string) ($data["permission"] ?? self::getPermission($titleId)),
                "voucher-material" => (string) ($data["voucher-material"] ?? "paper"),
                "voucher-name" => (string) ($data["voucher-name"] ?? "&r&l&eTitle Voucher: &r{display}"),
                "voucher-lore" => (array) ($data["voucher-lore"] ?? [
                    "&r&8Unlockable",
                    "",
                    "&r&8Claim this voucher to permanently unlock",
                    "&r{format}",
                ]),
            ];
        }

        self::registerVoucherDefinitions();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getTitles(): array{
        return self::$titles;
    }

    public static function exists(string $titleId): bool{
        return isset(self::$titles[self::normalizeId($titleId)]);
    }

    public static function get(string $titleId): ?array{
        return self::$titles[self::normalizeId($titleId)] ?? null;
    }

    public static function getPermission(string $titleId): string{
        return "glacia.title." . self::normalizeId($titleId);
    }

    public static function hasUnlocked(GlaciaPlayer $session, string $titleId): bool{
        $titleId = self::normalizeId($titleId);
        if($titleId === ""){
            return false;
        }

        $permission = (string) ((self::$titles[$titleId]["permission"] ?? self::getPermission($titleId)));
        return $session->hasExtraPermission($permission) || $session->hasUnlockedTitle($titleId);
    }

    public static function unlock(GlaciaPlayer $session, string $titleId): bool{
        $titleId = self::normalizeId($titleId);
        if($titleId === "" || !isset(self::$titles[$titleId]) || self::hasUnlocked($session, $titleId)){
            return false;
        }

        $permission = (string) self::$titles[$titleId]["permission"];
        $session->addExtraPermission($permission);
        $session->unlockTitle($titleId);
        return true;
    }

    private static function registerVoucherDefinitions(): void{
        foreach(array_keys(GlaciaItemFactory::$definitions) as $key){
            if(str_starts_with($key, "title_voucher_")){
                unset(GlaciaItemFactory::$definitions[$key]);
            }
        }

        foreach(self::$titles as $id => $data){
            $material = StringToItemParser::getInstance()->parse((string) $data["voucher-material"]) ?? VanillaItems::PAPER();
            $args = [
                "id" => $id,
                "display" => (string) $data["display"],
                "format" => (string) $data["format"],
                "permission" => (string) $data["permission"],
                "description" => (string) $data["description"],
            ];

            $lore = [];
            foreach((array) $data["voucher-lore"] as $line){
                $lore[] = self::template((string) $line, $args);
            }

            GlaciaItemFactory::$definitions["title_voucher_" . $id] = [
                "material" => $material,
                "name" => self::template((string) $data["voucher-name"], $args),
                "lore" => $lore,
                "nbt" => [
                    "glaciaItem" => "title_voucher",
                    "title_id" => $id,
                ],
            ];
        }
    }

    /**
     * @param array<string, string> $args
     */
    private static function template(string $template, array $args): string{
        foreach($args as $key => $value){
            $template = str_replace("{" . $key . "}", $value, $template);
        }

        return $template;
    }

    private static function normalizeId(string $id): string{
        return strtolower(trim(str_replace([" ", "-"], "_", $id)));
    }
}

