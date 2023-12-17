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
        $this->setGameStateValue(END_ROUND_TYPE, 0);
        $this->setGameStateValue(LAST_CHANCE_CALLER, 0);
        $this->setGameStateValue(STOP_CALLER, 0);
        $this->setGameStateValue(BET_RESULT, 0);

        // init round discard
        $cards = [];
        foreach([1, 2] as $discardNumber) {
            $card = $this->getCardFromDb($this->cards->pickCardForLocation('deck', 'discard'.$discardNumber));
            $cards[] = $card;

            self::notifyAllPlayers('cardInDiscardFromDeck', '', [
                'card' => $card,
                'discardId' => $discardNumber,
                'deckTopCard' => $this->getDeckTopCard(),
                'remainingCardsInDeck' => $this->getRemainingCardsInDeck(),
            ]);
        }

        $this->incStat(1, 'roundNumber');

        self::notifyAllPlayers('log', clienttranslate('A new round begins!'), []);
        self::notifyAllPlayers('log', clienttranslate('The cards ${cardColor1} ${cardName1} and ${cardColor2} ${cardName2} form the discard piles'), [
            'cardName1' => $this->getCardName($cards[0]),
            'cardName2' => $this->getCardName($cards[1]),
            'cardColor1' => $this->COLORS[$cards[0]->color],
            'cardColor2' => $this->COLORS[$cards[1]->color],
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

        if ($this->getGameStateValue(FORCE_TAKE_ONE) == $newPlayerId) {
            $this->setGameStateValue(FORCE_TAKE_ONE, 0);
        }

        if ($endRound == LAST_CHANCE) {
            $lastChanceCaller = intval($this->getGameStateValue(LAST_CHANCE_CALLER));

            $this->revealHand($playerId);

            if ($lastChanceCaller == $newPlayerId) {
                $this->activeNextPlayer();
                $this->gamestate->nextState('endRound');
                return;
            } else if ($lastChanceCaller == 0) {
                $this->setGameStateValue(LAST_CHANCE_CALLER, $playerId);
            }
        }

        $emptyDeck = false;
            if ($endRound == 0) {
            $emptyDeck = intval($this->cards->countCardInLocation('deck')) === 0;

            if ($emptyDeck) {
                $this->setGameStateValue(END_ROUND_TYPE, EMPTY_DECK);
            }
        }

        if ($endRound == STOP) {
            $endCaller = intval($this->getGameStateValue(STOP_CALLER));
            $this->revealHand($endCaller);
            $pId = intval($this->getPlayerAfter($endCaller));
            while ($pId != $endCaller) {
                $this->revealHand($pId);
                $pId = intval($this->getPlayerAfter($pId));
            }
        }

        $this->gamestate->nextState($emptyDeck || $endRound == STOP ? 'endRound' : 'newTurn');
    }

    function updateScores(int $endRound) {
        $playersIds = $this->getPlayersIds();
        $cardsPoints = [];
        foreach($playersIds as $playerId) {
            $cardsPoints[$playerId] = $this->getCardsPoints($playerId);
        }

        $playerPoints = array_map(fn($cardsPoint) => $cardsPoint->totalPoints, $cardsPoints);

        if ($endRound == LAST_CHANCE) {
            $lastChanceCaller = intval($this->getGameStateValue(LAST_CHANCE_CALLER));
            $betWon = $playerPoints[$lastChanceCaller] >= max($playerPoints);
            $this->setGameStateValue(BET_RESULT, $betWon ? 2 : 1);
            
            self::notifyAllPlayers('betResult', clienttranslate('${player_name} announced ${announcement}, and the bet is ${result}!'), [
                'playerId' => $lastChanceCaller,
                'player_name' => $this->getPlayerName($lastChanceCaller),
                'announcement' => $this->ANNOUNCEMENTS[LAST_CHANCE],
                'result' => $betWon ? clienttranslate('won') : clienttranslate('lost'),
                'i18n' => ['announcement', 'result'],
            ]);

            foreach($playersIds as $playerId) {
                $isBetCaller = $playerId == $lastChanceCaller;

                $messageOnlyColorBonus = clienttranslate('${player_name} only scores the color bonus of ${colorBonus} (${cardsPoints} cards points are ignored)');
                if ($betWon) {
                    $roundPoints = $isBetCaller ? 
                        $playerPoints[$playerId] + $cardsPoints[$playerId]->colorBonus : 
                        $cardsPoints[$playerId]->colorBonus;
                    $message = $isBetCaller ? 
                        clienttranslate('${player_name} won the bet and scores ${cardsPoints} for cards points, and the color bonus of ${colorBonus}') : 
                        $messageOnlyColorBonus;

                    $this->incPlayerScore($playerId, $roundPoints, $message, [
                        'roundPoints' => $roundPoints,
                        'cardsPoints' => $playerPoints[$playerId],
                        'colorBonus' => $cardsPoints[$playerId]->colorBonus,
                        'details' => $isBetCaller ? [
                            'cardsPoints' => $playerPoints[$playerId],
                            'colorBonus' => $cardsPoints[$playerId]->colorBonus,
                        ] : [
                            'cardsPoints' => null,
                            'colorBonus' => $cardsPoints[$playerId]->colorBonus,
                        ],
                    ]);

                    if ($isBetCaller) {
                        $this->incStat(1, 'lastChanceBetWon');
                        $this->incStat(1, 'lastChanceBetWon', $playerId);
                    }
                } else {

                    $roundPoints = $isBetCaller ? 
                        $cardsPoints[$playerId]->colorBonus : 
                        $playerPoints[$playerId];
                    $message = $isBetCaller ? 
                        $messageOnlyColorBonus : 
                        clienttranslate('${player_name} scores ${roundPoints} points in this round for cards points');

                    $this->incPlayerScore($playerId, $roundPoints, $message, [
                        'roundPoints' => $roundPoints,
                        'cardsPoints' => $playerPoints[$playerId],
                        'colorBonus' => $cardsPoints[$playerId]->colorBonus,
                        'details' => $isBetCaller ? [
                            'cardsPoints' => null,
                            'colorBonus' => $cardsPoints[$playerId]->colorBonus,
                        ] : [
                            'cardsPoints' =>  $roundPoints,
                            'colorBonus' => null,
                        ],
                    ]);

                    if ($isBetCaller) {
                        $this->incStat(1, 'lastChanceBetLost');
                        $this->incStat(1, 'lastChanceBetLost', $playerId);
                    }
                }
            }

        } else if ($endRound == STOP) {
            $endCaller = intval($this->getGameStateValue(STOP_CALLER));
            
            $this->notifyAllPlayers('log', clienttranslate('${player_name} announced ${announcement}, every player score the points for their cards'), [
                'playerId' => $endCaller,
                'player_name' => $this->getPlayerName($endCaller),
                'announcement' => $this->ANNOUNCEMENTS[STOP],
                'i18n' => ['announcement'],
            ]);

            foreach($playersIds as $playerId) {
                $roundPoints = $playerPoints[$playerId];                

                $this->incPlayerScore($playerId, $roundPoints, clienttranslate('${player_name} scores ${roundPoints} points in this round for cards points'), [
                    'roundPoints' => $roundPoints,
                    'details' => [
                        'cardsPoints' => $roundPoints,
                        'colorBonus' => null,
                    ],
                ]);
            }
        } else if ($endRound == EMPTY_DECK) {
            self::notifyAllPlayers('emptyDeck', clienttranslate('The round ends immediately without scoring because the deck is empty'), []);
        }
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
