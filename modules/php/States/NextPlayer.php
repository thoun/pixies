<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\StateType;
use Bga\Games\Pixies\Game;

class NextPlayer extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id: \ST_NEXT_PLAYER,
            type: StateType::GAME,
            name: 'nextPlayer',
        );
    }

    public function onEnteringState(int $activePlayerId): int|string
    {
        $this->game->giveExtraTime($activePlayerId);

        $tableCount = intval($this->game->cards->countCardInLocation('table'));
        $endTurn = $tableCount === 0;

        $playersIds = $this->game->getPlayersIds();
        if (!$endTurn && count($playersIds) === 2 && $tableCount === 2) {
            $endTurn = boolval($this->game->getGameStateValue((string)\LAST_TURN));
        }

        if (!$endTurn) {
            $this->game->activeNextPlayer();
        }

        return $endTurn ? EndTurn::class : ChooseCard::class;
    }
}
