<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\Pixies\Game;

class NewRound extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id: \ST_NEW_ROUND,
            type: StateType::GAME,
            name: 'newRound',
            updateGameProgression: true,
        );
    }

    public function onEnteringState(): string
    {
        $this->game->setGameStateValue((string)\LAST_TURN, 0);
        $this->bga->tableStats->inc('roundNumber', 1);

        $roundNumber = $this->bga->tableStats->get('roundNumber');

        $this->notify->all('newRound', clienttranslate('Round ${roundNumber}/3 begins!'), [
            'round' => $roundNumber,
            'roundNumber' => $roundNumber, // for logs
        ]);

        return NewTurn::class;
    }
}
