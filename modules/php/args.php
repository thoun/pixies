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

    function argChooseCard() {    
        return [
        ];
    }
   
    function argPlayCard() {
        $playerId = intval($this->getActivePlayerId());

        $card = $this->getGlobalVariable(SELECTED_CARD);
        $spaceCards = $this->getCardsFromSpace($playerId, $card->value);

        $spaces = [];
        if (count($spaceCards) < 2) {
            $spaces[] = $card->value;
        } else {
            for ($i = 1; $i <= 9; $i++) {
                if ($i != $card->value && count($this->getCardsFromSpace($playerId, $i)) == 0) {
                    $spaces[] = $i;
                }
            }
        }
    
        return [
            'spaces' => $spaces,
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
