<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\Pixies\Game;

class BeforeEndRound extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id: \ST_MULTIPLAYER_BEFORE_END_ROUND,
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
            description: clienttranslate('Some players are seeing end round result'),
            descriptionMyTurn: clienttranslate('End round result'),
            transitions: [
                'endRound' => EndRound::class,
                'endScore' => EndScore::class,
            ],
        );
    }

    public function onEnteringState(): ?string
    {
        $roundNumber = $this->bga->tableStats->get('roundNumber');
        $scoreRound = $this->scoreRound();

        foreach ($scoreRound as $playerId => $detailledScore) {
            $this->game->incPlayerScore(
                (int) $playerId,
                $detailledScore->points,
                clienttranslate('${player_name} gains ${incScore} points in this round'),
                ['detailledScore' => $detailledScore],
            );
        }

        $this->game->setGlobalVariable(\ROUND_RESULT . $roundNumber, $scoreRound);
        $this->bga->notify->all('roundResult', '', [
            'roundResult' => $scoreRound,
            'round' => $roundNumber,
        ]);

        if ($roundNumber >= 3) {
            return EndRound::class;
        }

        $this->gamestate->setAllPlayersMultiactive();

        return null;
    }

    #[PossibleAction]
    public function actSeen(int $currentPlayerId): void
    {
        $this->gamestate->setPlayerNonMultiactive($currentPlayerId, 'endRound');
    }

    public function zombie(int $playerId): void
    {
        $this->actSeen($playerId);
    }
    
    private function scoreRound() {
        $roundNumber = $this->bga->tableStats->get('roundNumber');
        $playersIds = $this->game->getPlayersIds();
        $result = [];
        $isFlowerPowerExpansion = $this->game->isFlowerPowerExpansion();

        foreach ($playersIds as $playerId) {
            $playerCards = $this->game->cardManager->getPlayerCards($playerId);
            $detailledScore = $this->game->cardManager->getDetailledScore($playerCards, $roundNumber, $isFlowerPowerExpansion);
            $result[$playerId] = $detailledScore;  
        
            $this->bga->playerStats->inc('pointsValidatedCard', $detailledScore->validatedCardPoints, $playerId, updateTableStat: true);
            $this->bga->playerStats->inc('pointsSpirals', $detailledScore->spiralsPoints, $playerId, updateTableStat: true);
            $this->bga->playerStats->inc('pointsLostCrosses', $detailledScore->crossesPoints, $playerId, updateTableStat: true);
            $this->bga->playerStats->inc('pointsColorZone', $detailledScore->largestColorZonePoints, $playerId, updateTableStat: true);
            $this->bga->playerStats->inc('pointsFacedownCards', $detailledScore->facedownCardsPoints, $playerId, updateTableStat: true);
        }

        return $result;
    }
}
