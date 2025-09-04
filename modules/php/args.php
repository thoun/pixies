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

    function getPossibleSpacesForCard(array $playerCards, $card): array {
        $spaceCards = $playerCards[$card->value];
        $spaces = [];
        if (count($spaceCards) < 2) {
            $spaces[] = $card->value;
        } else {
            for ($i = 1; $i <= 9; $i++) {
                if ($i != $card->value && count($playerCards[$i]) == 0) {
                    $spaces[] = $i;
                }
            }
        }

        return $spaces;
    }
   
    function argPlayCard() {
        $playerId = intval($this->getActivePlayerId());

        $card = $this->getSelectedCard();
        $playerCards = $this->getCardsFromSpaces($playerId);

        $spaces = $this->getPossibleSpacesForCard($playerCards, $card);
    
        return [
            'selectedCard' => $card,
            'spaces' => $spaces,
        ];
    }

    function argKeepCard() {
        $playerId = intval($this->getActivePlayerId());

        $card = $this->getSelectedCard();
        $spaceCards = $this->getCardsFromSpace($playerId, $card->value);

        return [
            'selectedCard' => $card,
            'cards' => [$spaceCards[0], $card],
        ];
    }
    
}
