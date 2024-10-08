<?php

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

    function emptyDeck() {
      $this->cards->moveAllCardsInLocation('deck', 'void');
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

    /*
     * loadBug: in studio, type loadBug(20762) into the table chat to load a bug report from production
     * client side JavaScript will fetch each URL below in sequence, then refresh the page
     */
    public function loadBug(int $reportId)
    {
      $db = explode('_', self::getUniqueValueFromDB("SELECT SUBSTRING_INDEX(DATABASE(), '_', -2)"));
      $game = $db[0];
      $tableId = $db[1];
      self::notifyAllPlayers(
        'loadBug',
        "Trying to load <a href='https://boardgamearena.com/bug?id=$reportId' target='_blank'>bug report $reportId</a>",
        [
          'urls' => [
            // Emulates "load bug report" in control panel
            "https://studio.boardgamearena.com/admin/studio/getSavedGameStateFromProduction.html?game=$game&report_id=$reportId&table_id=$tableId",
  
            // Emulates "load 1" at this table
            "https://studio.boardgamearena.com/table/table/loadSaveState.html?table=$tableId&state=1",
  
            // Calls the function below to update SQL
            "https://studio.boardgamearena.com/1/$game/$game/loadBugSQL.html?table=$tableId&report_id=$reportId",
  
            // Emulates "clear PHP cache" in control panel
            // Needed at the end because BGA is caching player info
            "https://studio.boardgamearena.com/admin/studio/clearGameserverPhpCache.html?game=$game",
  
            // Emulates "save 1" at this table
            //"https://studio.boardgamearena.com/table/table/debugSaveState.html?table=$tableId&state=1",
          ],
        ]
      );
    }
  
    /*
     * loadBugSQL: in studio, this is one of the URLs triggered by loadBug() above
     */
    public function loadBugSQL(int $reportId)
    {
      $studioPlayer = self::getCurrentPlayerId();
      $players = self::getObjectListFromDb('SELECT player_id FROM player', true);
  
      // Change for your game
      // We are setting the current state to match the start of a player's turn if it's already game over
      $sql = ['UPDATE global SET global_value=2 WHERE global_id=1 AND global_value=99'];
      $map = [];
      foreach ($players as $pId) {
        $map[(int) $pId] = (int) $studioPlayer;
  
        // All games can keep this SQL
        $sql[] = "UPDATE player SET player_id=$studioPlayer WHERE player_id=$pId";
        $sql[] = "UPDATE global SET global_value=$studioPlayer WHERE global_value=$pId";
        $sql[] = "UPDATE stats SET stats_player_id=$studioPlayer WHERE stats_player_id=$pId";
  
        // Add game-specific SQL update the tables for your game        
        for ($value = 1; $value <= 9; $value++) {
            $sql[] = "UPDATE card SET card_location='player-$studioPlayer-$value' WHERE card_location = 'player-$pId-$value'";
        }
  
        // This could be improved, it assumes you had sequential studio accounts before loading
        // e.g., quietmint0, quietmint1, quietmint2, etc. are at the table
        $studioPlayer++;
      }
      $msg =
        "<b>Loaded <a href='https://boardgamearena.com/bug?id=$reportId' target='_blank'>bug report $reportId</a></b><hr><ul><li>" .
        implode(';</li><li>', $sql) .
        ';</li></ul>';
      self::warn($msg);
      self::notifyAllPlayers('message', $msg, []);
  
      foreach ($sql as $q) {
        self::DbQuery($q);
      }
  
      self::reloadPlayersBasicInfos();
    }
}
