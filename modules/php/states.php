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
        $roundNumber = intval($this->getStat('roundNumber'));
        $playersIds = $this->getPlayersIds();

        foreach ($playersIds as $playerId) {
            $validatedCards = $this->getValidatedCards($playerId);

            $validatedCardPoints = 0;
            $spiralsAndCrossesPoints = 0;
            $largestColorZone = 0;
            $largestColorZonePoints = 0;

            foreach ($validatedCards as $space => $card) {
                if ($card) {
                    $validatedCardPoints += $space;

                    if ($card->spirals != 0) {
                        if ($card->spirals == -1) {
                            $spiralsAndCrossesPoints += count(array_filter($validatedCards, fn($c) => $c && in_array($c->color, [0, $card->color])));
                        } else {
                            $spiralsAndCrossesPoints += $card->spirals;
                        }
                    }
                    if ($card->crosses != 0) {
                        $spiralsAndCrossesPoints -= $card->crosses;
                    }

                    $colorZone = $this->getLargestColorZone($validatedCards);
                    if ($colorZone > $largestColorZone) {
                        $largestColorZone = $colorZone;
                    }
                }
            }

            $largestColorZonePoints = $largestColorZone * ($roundNumber + 1);

            $points = $validatedCardPoints + $spiralsAndCrossesPoints + $largestColorZonePoints;
            // TODO save points & detail ?
        }

        $lastRound = $roundNumber >= 3;
        
        if (!$lastRound) {
            $this->cards->moveAllCardsInLocation(null, 'deck');
            $this->cards->shuffle('deck');
        }

        self::notifyAllPlayers('endRound', '', [
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
