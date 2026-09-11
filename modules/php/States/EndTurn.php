<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\Pixies\Game;

class EndTurn extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id: \ST_END_TURN,
            type: StateType::GAME,
            name: 'endTurn',
        );
    }

    public function onEnteringState(): int|string
    {
        $this->bga->tableStats->inc('turnsNumber', 1);

        if (intval($this->game->cards->countCardInLocation('deck')) < count($this->game->getPlayersIds())) {
            $this->notify->all('log', clienttranslate('The deck is empty, so the round must end'), []);
            $this->game->setGameStateValue(\LAST_TURN, 1);
        }

        $endRound = boolval($this->game->getGameStateValue(\LAST_TURN));

        return $endRound ? \ST_MULTIPLAYER_BEFORE_END_ROUND : NewTurn::class;
    }
}
