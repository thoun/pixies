<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\Pixies\Game;

class EndScore extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id: \ST_END_SCORE,
            type: StateType::GAME,
            name: 'endScore',
        );
    }

    public function onEnteringState(): int
    {
        return \ST_END_GAME;
    }
}
