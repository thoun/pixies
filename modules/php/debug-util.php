<?php

trait DebugUtilTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    function debugSetup() {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        } 

        $this->d();

        $this->gamestate->changeActivePlayer(2343492);
    }

    function d() {
        $this->debugSetCard(2343492, 4, 2);
        $this->debugSetCard(2343492, 3, 2);
        $this->debugSetCard(2343492, 3, 4);
        $this->debugSetCard(2343492, 4, 5);
        $this->debugSetCard(2343492, 3, 10);
        $this->debugSetCard(2343492, 3, 12);
        $this->debugSetCard(2343492, 1, 14);
        $this->debugSetCard(2343492, 3, 16);

        $card7 = $this->debugGetCardByTypes(3, 14);
        $this->cards->moveCard($card7->id, 'table');
    }

    private function debugGetCardByTypes(int $color, int $index) {
        return $this->getCardsFromDb($this->cards->getCardsOfType($color, $index))[0];
    }

    private function debugSetCard(int $playerId, int $color, int $index, ?int $space = null, ?int $locationArg = null) {
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
            for ($value = 1; $value <= 9; $value++) {
			    $this->DbQuery("UPDATE card SET card_location='player-$sid-$value' WHERE card_location = 'player-$id-$value'" );
            }
			
			++$sid;
		}
	}

    function debug($debugData) {
        if ($this->getBgaEnvironment() != 'studio') { 
            return;
        }die('debug data : '.json_encode($debugData));
    }
}
