<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\Pixies\Game;

class NewTurn extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id: \ST_NEW_TURN,
            type: StateType::GAME,
            name: 'newTurn',
            updateGameProgression: true,
        );
    }

    public function onEnteringState(): string
    {
        $playerCount = $this->game->getPlayerCount();
        $cardCount = $playerCount === 2 ? 4 : $playerCount;

        $cards = $this->game->getCardsFromDb(
            $this->game->cards->pickCardsForLocation($cardCount, 'deck', 'table'),
        );

        $this->bga->notify->all('newTurn', '', [
            'cards' => $cards,
        ]);

        return ChooseCard::class;
    }
}
