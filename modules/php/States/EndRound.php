<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\Pixies\Game;

class EndRound extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id: \ST_END_ROUND,
            type: StateType::GAME,
            name: 'endRound',
        );
    }

    public function onEnteringState(): int|string
    {
        $roundNumber = $this->bga->tableStats->get('roundNumber');

        if ($roundNumber >= 3) {
            return EndScore::class;
        }

        $this->game->cardManager->reshuffleAllCardsToDeck();

        $this->bga->notify->all('endRound', '', [
            'remainingCardsInDeck' => $this->game->cardManager->getRemainingCardsInDeck(),
        ]);

        return NewRound::class;
    }
}
