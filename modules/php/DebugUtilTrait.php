<?php

namespace Bga\Games\Pixies;

use Bga\GameFramework\Actions\Debug;
use Bga\GameFramework\SystemException;

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

    #[Debug(reload: true)]
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
            $locationArg = count($this->cardManager->getCardsFromSpaces($playerId)[$row.'-'.$column]);
        }        
        $this->cards->moveCard($card->id, $location, $locationArg);
    }*/

    function debug_emptyDeck() {
      $this->cardManager->cards->moveAllItemsInLocation('deck', 'void');
    }

    function debug_playToNextTurn() {
      $round = $this->bga->tableStats->get('turnsNumber');
      $this->debug->playUntil(fn(int $count) => $this->bga->tableStats->get('turnsNumber') > $round);
    }

    function debug_playToNextRound() {
      $round = $this->bga->tableStats->get('roundNumber');
      $this->debug->playUntil(fn(int $count) => $this->bga->tableStats->get('roundNumber') > $round);
    }

    function debug_playToRoundScore() {
      $this->debug->playUntil(fn(int $count) => $this->gamestate->getCurrentMainStateId() >= ST_MULTIPLAYER_BEFORE_END_ROUND);
    }
}
