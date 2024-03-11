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
        $this->setGameStateValue(LAST_TURN, 0);

        $this->incStat(1, 'roundNumber');

        $roundNumber = intval($this->getStat('roundNumber'));

        self::notifyAllPlayers('newRound', clienttranslate('Round ${roundNumber}/3 begins!'), [
            'round' => $roundNumber,
            'roundNumber' => $roundNumber, // for logs
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
            if (boolval($this->getGameStateValue(LAST_TURN))) {
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

        if (intval($this->cards->countCardInLocation('deck')) < count($this->getPlayersIds())) {
            self::notifyAllPlayers('log', clienttranslate('The deck is empty, so the round must end'), []);
            $this->setGameStateValue(LAST_TURN, 1);
        }

        $endRound = boolval($this->getGameStateValue(LAST_TURN));

        $this->gamestate->nextState($endRound ? 'endRound' : 'newTurn');
    }
    

    function stBeforeEndRound() {
        $roundNumber = intval($this->getStat('roundNumber'));

        $scoreRound = $this->scoreRound();
        
        foreach ($scoreRound as $playerId => $detailledScore) {
            $this->incPlayerScore($playerId, $detailledScore->points, clienttranslate('${player_name} gains ${incScore} points in this round'), [                
                'detailledScore' => $detailledScore,
            ]);
        }
        $this->setGlobalVariable(ROUND_RESULT.$roundNumber, $scoreRound);
        self::notifyAllPlayers('roundResult', '', [
            'roundResult' => $scoreRound,
            'round' => $roundNumber,
        ]);

        $lastRound = $roundNumber >= 3;
        if ($lastRound) {
            $this->gamestate->nextState('endRound');
        } else {
            $this->gamestate->setAllPlayersMultiactive();
        }
    }

    function stEndRound() {
        $roundNumber = intval($this->getStat('roundNumber'));
        $lastRound = $roundNumber >= 3;

        if ($lastRound) {
            $this->gamestate->nextState('endScore');
        } else {
            $this->cards->moveAllCardsInLocation(null, 'deck');
            $this->cards->shuffle('deck');

            self::notifyAllPlayers('endRound', '', [
                'remainingCardsInDeck' => $this->getRemainingCardsInDeck(),
            ]);

            $this->gamestate->nextState('newRound');
        }
    }

    function stEndScore() {
        $this->gamestate->nextState('endGame');
    }
}
