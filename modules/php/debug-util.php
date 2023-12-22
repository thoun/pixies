<?php

trait DebugUtilTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    function debugSetup() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        } 

        //$this->debugFillHands();
        //$this->debugFillTable();
        //$this->debugSetMermaids();
        //$this->debugSetMermaidOnDeckTop();
        //$this->debugSetCardInHand(2343492, COLLECTION, SAILOR, ORANGE);
        //$this->debugSetCardInHand(2343492, COLLECTION, SAILOR, PINK);
        //$this->debugSetCardInHand(2343492, MULTIPLIER, CAPTAIN, LIGHT_ORANGE);

        //$this->debugSetCardInHand(2343492, COLLECTION, OCTOPUS, YELLOW);
        //$this->debugSetCardInHand(2343492, COLLECTION, OCTOPUS, PURPLE);
        //$this->debugSetCardInHand(2343492, COLLECTION, PENGUIN, PURPLE);
        //$this->debugSetCardInHand(2343492, COLLECTION, PENGUIN, PINK);
        //$this->debugSetCardInHand(2343492, MULTIPLIER, PENGUIN_COLONY, GREEN);
        //$this->debugSetCardInHand(2343492, SPECIAL, SEAHORSE, WHITE);
        //$this->debugSetCardInHand(2343492, MERMAID, 0, WHITE);

        /*$this->debugSetCardInHand(2343492, PAIR, SHARK, DARK_BLUE);
        $this->debugSetCardInHand(2343492, PAIR, SHARK, PURPLE);
        $this->debugSetCardInHand(2343492, PAIR, SWIMMER, LIGHT_BLUE);
        $this->debugSetCardInHand(2343492, PAIR, JELLYFISH, PURPLE);*/
        //$this->debugSetCardInHand(2343492, PAIR, CRAB, DARK_BLUE);
        //$this->debugSetCardInHand(2343492, PAIR, CRAB, LIGHT_BLUE);
        //$this->debugSetCardInHand(2343492, SPECIAL, STARFISH, YELLOW);*/
        //$this->debugSetCardInHand(2343492, PAIR, CRAB, BLACK);
        /*$this->debugSetCardInHand(2343492, COLLECTION, OCTOPUS, YELLOW);
        $this->debugSetCardInHand(2343492, COLLECTION, OCTOPUS, PURPLE);
        $this->debugSetCardInHand(2343492, SPECIAL, SEAHORSE, WHITE);
        $this->debugSetCardInHand(2343492, COLLECTION, SAILOR, PINK);
        $this->debugSetCardInHand(2343492, MULTIPLIER, CAPTAIN, LIGHT_ORANGE);*/

        $this->gamestate->changeActivePlayer(2343492);
    }

    function debugSetMermaids() {
        $playerId = 2343492;
        $number = 4;
        $cards = array_slice($this->getCardsFromDb(array_values($this->cards->getCardsOfType(10))), 0, $number);
        $this->cards->moveCards(array_map(fn($card) => $card->id, $cards), 'hand'.$playerId, 99);
    }

    function debugSetMermaidOnDeckTop() {
        $this->DbQuery("UPDATE card SET card_location_arg=1000 WHERE card_type = 10 AND card_location = 'deck' LIMIT 1" );
    }

    function debugSetMermaidsOnDeckTop() {
        $this->DbQuery("UPDATE card SET card_location_arg=1000 WHERE card_type = 10 AND card_location = 'deck'" );
    }

    private function debugGetCardByTypes($category, $family, $color, $index = 0) {
        return $this->getCardsFromDb($this->cards->getCardsOfType($category * 10 + $family, $color * 10 + $index))[0];
    }

    private function debugSetCardInHand($playerId, $category, $family, $color, $index = 0) {
        $card = $this->debugGetCardByTypes($category, $family, $color, $index);
        $this->cards->moveCard($card->id, 'hand'.$playerId);
    }

    function debugFillHands() {
        $number = 15;
        $playersIds = $this->getPlayersIds();
        foreach($playersIds as $playerId) {
            $playerId == 2343492 && $this->cards->pickCardsForLocation($number, 'deck', 'hand'.$playerId);
        }
    }

    function debugFillTable() {
        $number = 10;
        $playersIds = $this->getPlayersIds();
        foreach($playersIds as $playerId) {
            $this->cards->pickCardsForLocation($number, 'deck', 'table'.$playerId);
        }
    }

    function debugFillDiscards() {
        $number = 10;
        foreach([1, 2] as $pile) {
            $this->cards->pickCardsForLocation($number, 'deck', 'discard'.$pile);
        }
    }

    public function debugReplacePlayersIds() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        } 

		// These are the id's from the BGAtable I need to debug.
		$ids = array_map(fn($dbPlayer) => intval($dbPlayer['player_id']), array_values($this->getCollectionFromDb('select player_id from player order by player_no')));

		// Id of the first player in BGA Studio
		$sid = 2343492;
		
		foreach ($ids as $id) {
			// basic tables
			$this->DbQuery("UPDATE player SET player_id=$sid WHERE player_id = $id" );
			$this->DbQuery("UPDATE global SET global_value=$sid WHERE global_value = $id" );
			$this->DbQuery("UPDATE card SET card_location_arg=$sid WHERE card_location_arg = $id" );

			// 'other' game specific tables. example:
			// tables specific to your schema that use player_ids
			$this->DbQuery("UPDATE card SET card_location='table$sid' WHERE card_location = 'table$id'" );
			$this->DbQuery("UPDATE card SET card_location='hand$sid' WHERE card_location = 'hand$id'" );
			$this->DbQuery("UPDATE card SET card_location='tablehand$sid' WHERE card_location = 'tablehand$id'" );
			
			++$sid;
		}
	}

    function debug($debugData) {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        }die('debug data : '.json_encode($debugData));
    }
}
