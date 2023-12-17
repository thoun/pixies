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

    function stPlayCards() {
    }

    function stNextPlayer() {
        $playerId = intval($this->getActivePlayerId());

        $this->giveExtraTime($playerId);

        $this->incStat(1, 'turnsNumber');
        $this->incStat(1, 'turnsNumber', $playerId);

        $endRound = intval($this->getGameStateValue(END_ROUND_TYPE));

        $newPlayerId = $this->activeNextPlayer();

        $emptyDeck = false;
            if ($endRound == 0) {
            $emptyDeck = intval($this->cards->countCardInLocation('deck')) === 0;

            if ($emptyDeck) {
                $this->setGameStateValue(END_ROUND_TYPE, EMPTY_DECK);
            }
        }

        $this->gamestate->nextState($emptyDeck ? 'endRound' : 'newTurn');
    }

    function updateScores(int $endRound) {
        $playersIds = $this->getPlayersIds();
        $cardsPoints = [];
        foreach($playersIds as $playerId) {
            $cardsPoints[$playerId] = 0;
        }

        $playerPoints = array_map(fn($cardsPoint) => $cardsPoint->totalPoints, $cardsPoints);
    }

    function isLastRound() {
        $maxScore = 100;
        $topScore = $this->getPlayerTopScore();

        return $topScore >= $maxScore;
    }

    function stBeforeEndRound() {
        $endRound = intval($this->getGameStateValue(END_ROUND_TYPE));
        $this->updateScores($endRound);

        if ($this->isLastRound()) {
            $this->gamestate->nextState('endScore');
        } else {
            $this->setGameStateValue(FORCE_TAKE_ONE, 0);
            $this->gamestate->setAllPlayersMultiactive();
        }
    }

    function stEndRound() {
        $lastRound = $this->isLastRound();
        if (!$lastRound) {
            $this->cards->moveAllCardsInLocation(null, 'deck');
            $this->cards->shuffle('deck');
        }

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
