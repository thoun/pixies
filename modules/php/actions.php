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

        $this->setGlobalVariable(SELECTED_CARD, $card);

        self::notifyPlayer($playerId, 'chooseCard', '', [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $card,
        ]);

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
        $card = $this->getGlobalVariable(SELECTED_CARD);

        $count = intval($this->cards->countCardInLocation("player-$playerId-$card->value"));
        $this->cards->moveCard($card->id, "player-$playerId-$card->value", $count);

        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays card on space ${value}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $card,
            'value' => $card->value,
        ]);

        $this->gamestate->nextState('next');
    }

    public function endTurn() {
        $this->checkAction('endTurn'); 

        $playerId = intval($this->getActivePlayerId());
        
        $this->gamestate->nextState('endTurn');
    }

    private function applyEndRound(int $type, string $announcement) {
        $playerId = intval($this->getActivePlayerId());

        $this->setGameStateValue(END_ROUND_TYPE, $type);

        self::notifyAllPlayers('announceEndRound', clienttranslate('${player_name} announces ${announcement}!'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'announcement' => $announcement,
            'i18n' => ['announcement'],
        ]);
        
        $this->gamestate->nextState('endTurn');
    }

    public function endRound() {
        $this->checkAction('endRound');

        $playerId = intval($this->getActivePlayerId());

        $this->incStat(1, 'announce');
        $this->incStat(1, 'announce', $playerId);
        $this->incStat(1, 'announceLastChance');
        $this->incStat(1, 'announceLastChance', $playerId);

        $this->applyEndRound(LAST_CHANCE, $this->ANNOUNCEMENTS[LAST_CHANCE]);
    }

    public function chooseOpponent(int $opponentId) {
        $this->checkAction('chooseOpponent');

        $playerId = intval($this->getActivePlayerId());

        $this->applySteal($playerId, $opponentId);

        $this->gamestate->nextState('playCard');
    }

    public function seen() {
        $this->checkAction('seen');

        $playerId = intval($this->getCurrentPlayerId());

        $this->gamestate->setPlayerNonMultiactive($playerId, 'endRound');
    }
}
