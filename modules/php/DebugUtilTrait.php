<?php

namespace Bga\Games\Pixies;

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

    /*private function debug_setCard(int $playerId, int $color, int $index, ?int $space = null, ?int $locationArg = null) {
        $card = $this->debugGetCardByTypes($color, $index);
        if ($space === null) {
            $space = $card->value;
        }
        $location = "player-$playerId-$space";
        if ($locationArg === null) {
            $locationArg = count($this->cardManager->getCardsFromSpace($playerId, $row, $column));
        }        
        $this->cards->moveCard($card->id, $location, $locationArg);
    }*/

    function debug_emptyDeck() {
      $this->cards->moveAllCardsInLocation('deck', 'void');
    }

    function debug_playToEndRound() {
      while ($this->gamestate->getCurrentMainStateId() < ST_MULTIPLAYER_BEFORE_END_ROUND) {
        $playerId = intval($this->getActivePlayerId());
        $state = $this->gamestate->getCurrentMainState();
        if ($state === null) {
          throw new \BgaSystemException('Current game state not found');
        }
        $this->gamestate->runStateClassZombie($state, $playerId);
      }
    }
}
