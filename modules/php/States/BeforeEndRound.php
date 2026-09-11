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
            name: 'beforeEndRound',
            description: clienttranslate('Some players are seeing end round result'),
            descriptionMyTurn: clienttranslate('End round result'),
            transitions: [
                'next' => EndRound::class,
                'endRound' => EndRound::class,
                'endScore' => EndScore::class,
            ],
        );
    }

    public function onEnteringState(): ?string
    {
        $roundNumber = $this->bga->tableStats->get('roundNumber');
        $scoreRound = $this->game->scoreRound();

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
        $this->gamestate->setPlayerNonMultiactive($playerId, 'next');
    }
}
