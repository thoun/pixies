<?php

trait ActionTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    
    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in nicodemus.action.php)
    */

    public function chooseCard(int $cardId) {
        $this->checkAction('chooseCard'); 
        
        $playerId = intval($this->getActivePlayerId());

        $card = $this->getCardFromDb($this->cards->getCard($cardId));
        if ($card->location != 'table') {
            throw new BgaUserException("You annot choose this card");
        }
        
        $stateName = $this->gamestate->state()['name']; 
        $isChangeOfCard = $stateName === 'playCard' || $stateName === 'keepCard';
        if ($isChangeOfCard) {
            $this->gamestate->nextState('cancel');
        }

        $this->applyChooseCard($playerId, $card);
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

    public function playCard(int $space) {
        $this->checkAction('playCard'); 

        $args = $this->argPlayCard();
        if (!in_array($space, $args['spaces'])) {
            throw new BgaUserException("Invalid space");
        }

        $playerId = intval($this->getActivePlayerId());

        $this->applyPlayCard($playerId, $space);
    }

    public function applyPlayCard(int $playerId, int $space) {
        $card = $this->getSelectedCard();

        $count = intval($this->cards->countCardInLocation("player-$playerId-$space"));
        $this->cards->moveCard($card->id, "player-$playerId-$space", $count);
        $card->locationArg = $count;

        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays a ${color} card on space ${value}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $space == $card->value ? $card : Card::onlyId($card),
            'space' => $space,
            'color' => $this->COLORS[$card->color], // for log
            'value' => $space, // for log
            'i18n' => ['color'],
        ]);

        $this->gamestate->nextState('next');
    }

    public function keepCard(int $index) {
        $this->checkAction('keepCard');

        if (!in_array($index, [0, 1])) {
            throw new BgaUserException("Invalid index");
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

        self::notifyAllPlayers('keepCard', clienttranslate('${player_name} keeps the ${color} card on space ${value}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'hiddenCard' => Card::onlyId($hiddenCard),
            'visibleCard' => $visibleCard,
            'space' => $space,
            'color' => $this->COLORS[$card->color], // for log
            'value' => $space, // for log
            'i18n' => ['color'],
        ]);

        $this->gamestate->nextState('next');
    }

    public function seen() {
        $this->checkAction('seen');

        $playerId = intval($this->getCurrentPlayerId());

        $this->gamestate->setPlayerNonMultiactive($playerId, 'endRound');
    }

    public function cancel() {
        $this->checkAction('cancel');

        $this->gamestate->nextState('cancel');
    }
}
