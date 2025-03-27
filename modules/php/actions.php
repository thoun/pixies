<?php

trait ActionTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    
    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in nicodemus.action.php)
    */

    public function actChooseCard(int $id, bool $autoplace) {        
        $playerId = intval($this->getActivePlayerId());

        $card = $this->getCardFromDb($this->cards->getCard($id));
        if ($card->location != 'table') {
            throw new \BgaUserException("You cannot choose this card");
        }
        
        $stateName = $this->gamestate->state()['name']; 
        $isChangeOfCard = $stateName === 'playCard' || $stateName === 'keepCard';
        if ($isChangeOfCard) {
            $this->gamestate->nextState('cancel');
        }

        $this->applyChooseCard($playerId, $card);

        if ($autoplace && $this->gamestate->state()['name'] === 'playCard') {
            $spaces = $this->argPlayCard()['spaces'];
            if (count($spaces) == 1) {
                $this->applyPlayCard($playerId, $spaces[0]);
            }
        }
    }

    function applyChooseCard(int $playerId, Card $card) {
        $this->setGlobalVariable(SELECTED_CARD_ID, $card->id);

        $spaceCards = $this->getCardsFromSpace($playerId, $card->value);

        if (count($spaceCards) == 1 && $spaceCards[0]->value == $card->value) {
            $this->gamestate->nextState('keepCard');
        } else  {
            $this->gamestate->nextState('playCard');
        }
    }

    public function actPlayCard(int $space) {

        $args = $this->argPlayCard();
        if (!in_array($space, $args['spaces'])) {
            throw new \BgaUserException("Invalid space");
        }

        $playerId = intval($this->getActivePlayerId());

        $this->applyPlayCard($playerId, $space);
    }

    public function applyPlayCard(int $playerId, int $space) {
        $card = $this->getSelectedCard();

        $count = intval($this->cards->countCardInLocation("player-$playerId-$space"));
        $this->cards->moveCard($card->id, "player-$playerId-$space", $count);
        $card->locationArg = $count;

        $statName = $space == $card->value ? 'cardPlayedEmptySpaceVisible' : 'cardPlayedEmptySpaceHidden';
        $this->incStat(1, $statName);
        $this->incStat(1, $statName, $playerId);

        $this->notify->all('playCard', clienttranslate('${player_name} plays a ${color} card on space ${value}'), [
            'playerId' => $playerId,
            'card' => $space == $card->value ? $card : Card::onlyId($card),
            'space' => $space,
            'visibleCard' => $card, // only used for logs
        ]);

        if (!boolval($this->getGameStateValue(LAST_TURN)) && $this->getPlayerCardCount($playerId) >= 9) {
            $this->setGameStateValue(LAST_TURN, 1);

            $this->notify->all('lastTurn', clienttranslate('${player_name} has filled all 9 of their spaces, triggering the end of the round!'), [
                'playerId' => $playerId,
            ]);
        }

        $this->gamestate->nextState('next');
    }

    public function actKeepCard(int $index) {

        if (!in_array($index, [0, 1])) {
            throw new \BgaUserException("Invalid index");
        }

        $playerId = intval($this->getActivePlayerId());

        $this->applyKeepCard($playerId, $index);
    }

    public function applyKeepCard(int $playerId, int $index) {
        $card = $this->getSelectedCard();
        $space = $card->value;
        $spaceCard = $this->getCardsFromSpace($playerId, $space)[0];

        $hiddenCard = $index == 0 ? $card : $spaceCard;
        $visibleCard = $index == 1 ? $card : $spaceCard;

        $hiddenCard->locationArg = 0;
        $visibleCard->locationArg = 1;

        $this->cards->moveCard($hiddenCard->id, "player-$playerId-$space", $hiddenCard->locationArg);
        $this->cards->moveCard($visibleCard->id, "player-$playerId-$space", $visibleCard->locationArg);

        $this->notify->all('keepCard', clienttranslate('${player_name} keeps the ${color} card on space ${value}'), [
            'playerId' => $playerId,
            'hiddenCard' => Card::onlyId($hiddenCard),
            'visibleCard' => $visibleCard,
            'space' => $space,
        ]);
        
        $this->incStat(1, 'validatedCard');
        $this->incStat(1, 'validatedCard', $playerId);

        $this->gamestate->nextState('next');
    }

    public function actSeen() {
        $playerId = intval($this->getCurrentPlayerId());

        $this->gamestate->setPlayerNonMultiactive($playerId, 'endRound');
    }

    public function actCancel() {
        $this->gamestate->nextState('cancel');
    }
}
