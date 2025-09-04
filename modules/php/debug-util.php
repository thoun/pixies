<?php

function debug(...$debugData) {
    if (\Bga\GameFramework\Table::getBgaEnvironment() != 'studio') { 
        return;
    }die('debug data : <pre>'.substr(json_encode($debugData, JSON_PRETTY_PRINT), 1, -1).'</pre>');
}

trait DebugUtilTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    function debugSetup() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        } 

        //$this->d();

        //$this->gamestate->changeActivePlayer(2343492);
    }

    function debug_setCardType(int $id, int $type, int $typeArg = 0) {
      $this->DbQuery("UPDATE card SET card_type = $type, card_type_arg = $typeArg WHERE card_id = $id" );
    }

    function d() {
        $this->debug_setCard(2343492, 4, 2);
        $this->debug_setCard(2343492, 3, 2);
        $this->debug_setCard(2343492, 3, 4);
        $this->debug_setCard(2343492, 4, 5);
        $this->debug_setCard(2343492, 3, 10);
        $this->debug_setCard(2343492, 3, 12);
        $this->debug_setCard(2343492, 1, 14);
        $this->debug_setCard(2343492, 3, 16);

        $card7 = $this->debugGetCardByTypes(3, 14);
        $this->cards->moveCard($card7->id, 'table');
    }

    private function debugGetCardByTypes(int $color, int $index) {
        return $this->getCardsFromDb($this->cards->getCardsOfType($color, $index))[0];
    }

    private function debug_setCard(int $playerId, int $color, int $index, ?int $space = null, ?int $locationArg = null) {
        $card = $this->debugGetCardByTypes($color, $index);
        if ($space === null) {
            $space = $card->value;
        }
        $location = "player-$playerId-$space";
        if ($locationArg === null) {
            $locationArg = intval($this->cards->countCardInLocation("player-$playerId-$space"));
        }        
        $this->cards->moveCard($card->id, $location, $locationArg);
    }

    function debug_emptyDeck() {
      $this->cards->moveAllCardsInLocation('deck', 'void');
    }

    function debug_playToEndRound() {
      while (intval($this->gamestate->state_id()) < ST_MULTIPLAYER_BEFORE_END_ROUND) {
        $state = intval($this->gamestate->state_id());
        switch ($state) {
          case ST_PLAYER_CHOOSE_CARD:
            $playerId = intval($this->getActivePlayerId());
            $this->zombieTurn_chooseCard($playerId);
            break;
    
          case ST_PLAYER_PLAY_CARD:
            $playerId = intval($this->getActivePlayerId());
            $this->zombieTurn_playCard($playerId);
            break;

          case ST_PLAYER_KEEP_CARD:
            $playerId = intval($this->getActivePlayerId());
            $this->zombieTurn_keepCard($playerId);
            break;
        }
      }
    }
}
