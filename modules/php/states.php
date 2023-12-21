<?php

trait StateTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stNewRound() {

        $this->incStat(1, 'roundNumber');

        self::notifyAllPlayers('newRound', clienttranslate('Round ${round}/3 begins!'), [
            'round' => $this->getStat('roundNumber')
        ]);

        $this->gamestate->nextState('start');
    }   
    
    function stNewTurn() {
        $playerCount = count($this->getPlayersIds());
        $cardCount = $playerCount == 2 ? 4 : $playerCount;

        $cards = $this->getCardsFromDb($this->cards->pickCardsForLocation($cardCount, 'deck', 'table'));

        self::notifyAllPlayers('newTurn', '', [
            'cards' => $cards,
        ]);

        $this->gamestate->nextState('start');
    }

    function stNextPlayer() {
        $playerId = intval($this->getActivePlayerId());

        $this->giveExtraTime($playerId);

        $tableCount = intval($this->cards->countCardInLocation('table'));
        $endTurn = $tableCount == 0;

        $playersIds = $this->getPlayersIds();
        if (!$endTurn && count($playersIds) == 2 && $tableCount == 2) {
            if ($this->array_some($playersIds, fn($pId) => $this->array_every($this->getCardsFromSpaces($pId), fn($space) => count($space) > 0))) {
                $endTurn = true;
            }
        }

        if (!$endTurn) {
            $this->activeNextPlayer();
        }

        $this->gamestate->nextState($endTurn ? 'endTurn' : 'next');
    } 
    
    function stEndTurn() {
        $this->incStat(1, 'turnsNumber');

        $playersIds = $this->getPlayersIds();
        $endRound = $this->array_some($playersIds, fn($pId) => $this->array_every($this->getCardsFromSpaces($pId), fn($space) => count($space) > 0));

        $this->gamestate->nextState($endRound ? 'endRound' : 'newTurn');
    }

    function stEndRound() {
        $lastRound = $this->getStat('roundNumber') >= 3;

        self::notifyAllPlayers('endRound', '', [
            'deckTopCard' => $this->getDeckTopCard(),
            'remainingCardsInDeck' => $this->getRemainingCardsInDeck(),
        ]);

        $this->gamestate->nextState($lastRound ? 'endScore' : 'newRound');
    }

    function stEndScore() {
        $playersIds = $this->getPlayersIds();

        foreach ($playersIds as $playerId) {
        }

        $this->gamestate->nextState('endGame');
    }
}
