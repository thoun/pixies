<?php

trait ArgsTrait {
    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */
   
    function argTakeCards() {
        $forceTakeOne = intval($this->getGameStateValue(FORCE_TAKE_ONE)) > 0;

        $canTakeFromDeck = intval($this->cards->countCardInLocation('deck')) > 0;
        $canTakeFromDiscard = [];
        foreach([1, 2] as $discardNumber) {
            if (intval($this->cards->countCardInLocation('discard'.$discardNumber)) > 0) {
                $canTakeFromDiscard[] = $discardNumber;
            }
        }
        $endRound = intval($this->getGameStateValue(END_ROUND_TYPE));
    
        return [
            'forceTakeOne' => $forceTakeOne,
            'canTakeFromDeck' => !$forceTakeOne && $canTakeFromDeck,
            'canTakeFromDiscard' => $forceTakeOne ? [] : $canTakeFromDiscard,
            'call' => in_array($endRound, [LAST_CHANCE, STOP]) ? $this->ANNOUNCEMENTS[$endRound] : '',
        ];
    }

    function argChooseCard() {        
        $playerId = intval($this->getActivePlayerId());

        $cards = $this->getCardsFromDb($this->cards->getCardsInLocation('pick'));
        $maskedCards = Card::onlyIds($cards);
    
        return [
            '_private' => [
                $playerId => [
                    'cards' => $cards,
                ]
            ],
            'cards' => $maskedCards,
            'deckTopCard' => $this->getDeckTopCard(),
            'remainingCardsInDeck' => $this->getRemainingCardsInDeck(),
        ];
    }
   
    function argPlayCards() {
        $playerId = intval($this->getActivePlayerId());

        $totalPoints = $this->getCardsPoints($playerId)->totalPoints;
        $playableDuoCards = $this->playableDuoCards($playerId);
        $canCallEndRound = $totalPoints >= 7 && intval($this->getGameStateValue(END_ROUND_TYPE)) == 0;
    
        return [
            'canDoAction' => count($playableDuoCards) > 0 || $canCallEndRound || $hasFourMermaids,
            'playableDuoCards' => $playableDuoCards,
            'canCallEndRound' => $canCallEndRound,
        ];
    }

    function argChooseDiscardCard() {
        $playerId = intval($this->getActivePlayerId());

        $discardNumber = $this->getGameStateValue(CHOSEN_DISCARD);
        $cards = $this->getCardsFromDb($this->cards->getCardsInLocation('discard'.$discardNumber, null, 'location_arg'));
        $maskedCards = Card::onlyIds($cards);
    
        return [
            'discardNumber' => $discardNumber,
            '_private' => [
                $playerId => [
                    'cards' => $cards,
                ]
            ],
            'cards' => $maskedCards,
        ];
    }

    function argChooseOpponent() {
        $playerId = intval($this->getActivePlayerId());

        $possibleOpponentsToSteal = $this->getPossibleOpponentsToSteal($playerId);

        return [
            'playersIds' => $possibleOpponentsToSteal,
        ];
    }
    
}
