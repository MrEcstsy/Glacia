<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\faction;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use pocketmine\player\Player;
use Vecnavium\FormsUI\SimpleForm;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\sound\ExplodeSound;
use ecstsy\Glacia\player\GlaciaPlayer;

final class DisbandConfirmationScreen extends BaseScreen {

    private SimpleForm $form;

    public function __construct(Player $player)
    {
        $session = Loader::getPlayerManager()->getSession($player);
        $factionId = $session->getFactionId();

        $this->form = new SimpleForm(function (Player $player, ?int $data) use ($factionId): void {
            if ($data === null) return;

            switch ($data) {
                case 0:
                    $faction = FactionManager::getInstance()->getFaction($factionId);
                    if ($faction === null) {
                        $player->sendMessage(C::colorize("&r&cFaction no longer exists."));
                        return;
                    }

                    foreach (Loader::getPlayerManager()->getSessions() as $session) {
                        /** @var ?GlaciaPlayer $session */
                        if ($session->getFactionId() === $factionId) {
                            $member = $session->getPlayer();
                            if ($member instanceof Player) {
                                $member->sendMessage(C::colorize("&r&cYour faction &4{$faction->getName()} &chas been disbanded."));
                            }
                            $session->joinFaction(null, null);
                        }
                    }

                    $db = Loader::getDatabase();

                    FactionManager::getInstance()->deleteFaction($factionId);
                    $db->executeChange(QueryStmts::FACTION_MEMBERS_DELETE, ["faction_id" => $factionId, "member_uuid" => $player->getUniqueId()->toString()]);
                    $db->executeChange(QueryStmts::FACTION_ROLES_DELETE, ["faction_id" => $factionId]);
                    $db->executeChange(QueryStmts::FACTION_HOME_DELETE, ["faction_id" => $factionId]);
                    $db->executeChange(QueryStmts::FACTION_CLAIM_DELETE_FACTION, ["faction_id" => $factionId]);

                    $player->sendMessage(C::colorize("&r&cYou disbanded the faction &4{$faction->getName()}"));
                    $player->getWorld()->addSound($player->getPosition(), new ExplodeSound());
                    $player->getWorld()->addParticle($player->getEyePos(), new HugeExplodeParticle());
                    break;
            }
        });

        $this->form->setTitle(C::colorize("&r&l&8FACTION DISBAND"));
        $this->form->setContent(C::colorize("&r&fAre you sure you want to disband your faction?"));
        $this->form->addButton(C::colorize("&r&c&lCONFIRM"));
        $this->form->addButton(C::colorize("&r&8&lCANCEL"));
    }

    public function getForm(): SimpleForm
    {
        return $this->form;
    }

    public function displayForm(Player $player): void {
        $screen = new self($player);
        $player->sendForm($screen->getForm());
    }
}