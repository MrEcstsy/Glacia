<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\factions\admin\ForceClaimSubCommand;
use ecstsy\Glacia\commands\sub\factions\admin\ForceDisbandSubCommand;
use ecstsy\Glacia\commands\sub\factions\admin\ForceUnclaimSubCommand;
use ecstsy\Glacia\commands\sub\factions\ChatSubCommand;
use ecstsy\Glacia\commands\sub\factions\claim\ClaimSubCommand;
use ecstsy\Glacia\commands\sub\factions\claim\UnClaimSubCommand;
use ecstsy\Glacia\commands\sub\factions\CreateSubCommand;
use ecstsy\Glacia\commands\sub\factions\DemoteSubCommand;
use ecstsy\Glacia\commands\sub\factions\DescriptionSubCommand;
use ecstsy\Glacia\commands\sub\factions\DisbandSubCommand;
use ecstsy\Glacia\commands\sub\factions\HelpSubCommand;
use ecstsy\Glacia\commands\sub\factions\InviteSubCommand;
use ecstsy\Glacia\commands\sub\factions\JoinSubCommand;
use ecstsy\Glacia\commands\sub\factions\KickSubCommand;
use ecstsy\Glacia\commands\sub\factions\LeaveSubCommand;
use ecstsy\Glacia\commands\sub\factions\PromoteSubCommand;
use ecstsy\Glacia\commands\sub\factions\relations\AllyAcceptSubCommand;
use ecstsy\Glacia\commands\sub\factions\relations\AllyDenySubCommand;
use ecstsy\Glacia\commands\sub\factions\relations\AllySubCommand;
use ecstsy\Glacia\commands\sub\factions\relations\EnemySubCommand;
use ecstsy\Glacia\commands\sub\factions\relations\NeutralSubCommand;
use ecstsy\Glacia\commands\sub\factions\RenameSubCommand;
use ecstsy\Glacia\commands\sub\factions\admin\SetPowerSubCommand;
use ecstsy\Glacia\commands\sub\factions\MapSubCommand;
use ecstsy\Glacia\commands\sub\factions\TransferSubCommand;
use ecstsy\Glacia\commands\sub\factions\TopSubCommand;
use ecstsy\Glacia\commands\sub\factions\WhoSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class FactionCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new IntegerArgument("page", true));

        $plugin = Loader::getInstance();
        $this->registerSubCommand(new CreateSubCommand($plugin, "create", "Create a faction"));
        $this->registerSubCommand(new WhoSubCommand($plugin, "info", "Obtain faction info", ["who", "whois"]));
        $this->registerSubCommand(new InviteSubCommand($plugin, "invite", "Invite a player to your faction", ["i"]));
        $this->registerSubCommand(new JoinSubCommand($plugin, "join", "Join a faction", ["j", "accept"]));
        $this->registerSubCommand(new LeaveSubCommand($plugin, "leave", "Leave a faction", ["l"]));
        $this->registerSubCommand(new DisbandSubCommand($plugin, "disband", "Disband your faction", ["d"]));
        $this->registerSubCommand(new ClaimSubCommand($plugin, "claim", "Claim a chunk"));
        $this->registerSubCommand(new UnClaimSubCommand($plugin, "unclaim", "Unclaim a chunk"));
        $this->registerSubCommand(new AllySubCommand($plugin, "ally", "Form an alliance with another faction", ["a"]));
        $this->registerSubCommand(new NeutralSubCommand($plugin, "neutral", "Form a neutral relationship with another faction", ["n"]));
        $this->registerSubCommand(new AllyAcceptSubCommand($plugin, "allyaccept", "Accept an alliance with another faction", ["allyok", "allyyes"]));
        $this->registerSubCommand(new EnemySubCommand($plugin, "enemy", "Declare another faction as enemy", ["e"]));
        $this->registerSubCommand(new ChatSubCommand($plugin, "chat", "Toggle faction related chats", ["c"]));
        $this->registerSubCommand(new HelpSubCommand($plugin, "help", "Show list of faction commands"));
        $this->registerSubCommand(new DemoteSubCommand($plugin, "demote", "Demote a player in your faction"));
        $this->registerSubCommand(new PromoteSubCommand($plugin, "promote", "Promote a player in your faction"));
        $this->registerSubCommand(new KickSubCommand($plugin, "kick", "Kick a player from your faction"));
        $this->registerSubCommand(new DescriptionSubCommand($plugin, "description", "Set your faction's description"));
        $this->registerSubCommand(new RenameSubCommand($plugin, "rename", "Rename your faction"));
        $this->registerSubCommand(new TransferSubCommand($plugin, "transfer", "Transfer leadership of your faction"));
        $this->registerSubCommand(new TopSubCommand($plugin, "top", "View the top factions"));
        $this->registerSubCommand(new AllyDenySubCommand($plugin, "allydeny", "Deny an alliance request from another faction", ["allyno", "allycancel"]));
        $this->registerSubCommand(new ForceClaimSubCommand($plugin, "forceclaim", "Force claim a chunk"));
        $this->registerSubCommand(new ForceUnclaimSubCommand($plugin, "forceunclaim", "Force unclaim a chunk"));
        $this->registerSubCommand(new SetPowerSubCommand($plugin, "setpower", "Set power for a player"));
        $this->registerSubCommand(new ForceDisbandSubCommand($plugin, "forcedisband", "Force disband a faction"));
        $this->registerSubCommand(new MapSubCommand($plugin, "map", "Show faction map"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $page = isset($args["page"]) && $args["page"] > 0 ? (int)$args["page"] : 1;
        $perPage = 5;

        $session = Loader::getPlayerManager()->getSession($sender);
        $facName = $session?->getFaction()?->getName() ?? "GLACIA &fFACTIONS";

        $header = C::colorize("&r&8&l-=-=-=&7&l-=-=-= &b{$facName} &7&l-=-=-=&8&l-=-=-=&r");
        $sender->sendMessage($header);

        $unique = [];
        foreach ($this->getSubCommands() as $sub) {
            $unique[spl_object_id($sub)] = $sub;
        }

        $subs = [];
        foreach ($unique as $sub) {
            $subs[$sub->getName()] = $sub;
        }

        $total = count($subs);
        $maxPage = (int)ceil($total / $perPage);

        if ($page > $maxPage) {
            $sender->sendMessage(GlaciaUtils::format('faction.error.page_not_found', ["page" => $page, "maxPage" => $maxPage]));
            return;
        }

        $slice = array_slice($subs, ($page - 1) * $perPage, $perPage, true);
        foreach ($slice as $name => $sub) {
            $usage = $this->buildUsage($aliasUsed, $name, $sub->getArgumentList());
            $desc = $sub->getDescription();
            $sender->sendMessage(C::colorize("&f{$usage} &7- &r{$desc}"));
        }

        if ($page < $maxPage) {
            $sender->sendMessage(C::colorize("    &7((&o use &b/{$aliasUsed} help " . ($page + 1) . " &7to view more commands ))"));
        }
    }

    private function buildUsage(string $alias, string $name, array $argLists): string {
        $usage = "&8/&b{$alias} &f{$name}";
        if (empty($argLists)) return $usage;

        $first = reset($argLists);
        $parts = [];

        foreach ($first as $arg) {
            if ($arg instanceof BaseArgument) {
                $param = $arg->getName();
                $wrap = $arg->isOptional() ? ['[', ']'] : ['<', '>'];
                $parts[] = "&7{$wrap[0]}&b{$param}&7{$wrap[1]}";
            }
        }

        return $usage . ' ' . implode(' ', $parts);
    }

    public function getPermission(): ?string {
        return "glacia.default";
    }
}
