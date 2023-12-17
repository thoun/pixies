<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * Pixies implementation : © <Your name here> <Your email address here>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  * 
  * pixies.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );

require_once('modules/php/constants.inc.php');
require_once('modules/php/utils.php');
require_once('modules/php/actions.php');
require_once('modules/php/states.php');
require_once('modules/php/args.php');
require_once('modules/php/debug-util.php');

class Pixies extends Table {
    use UtilTrait;
    use ActionTrait;
    use StateTrait;
    use ArgsTrait;
    use DebugUtilTrait;

	function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        
        self::initGameStateLabels([
            CHOSEN_DISCARD => CHOSEN_DISCARD,
            END_ROUND_TYPE => END_ROUND_TYPE,
            LAST_CHANCE_CALLER => LAST_CHANCE_CALLER,
            STOP_CALLER => STOP_CALLER,
            BET_RESULT => BET_RESULT,
            FORCE_TAKE_ONE => FORCE_TAKE_ONE,
            LOBSTER_POWER => LOBSTER_POWER,

            EXPANSION => EXPANSION,
            DOUBLE_POINTS => DOUBLE_POINTS,
        ]);  

        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");        
	}
	
    protected function getGameName() {
		// Used for translations and stuff. Please do not modify.
        return "pixies";
    }	

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = []) {    
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player ) {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode(',', $values);
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        $this->setGameStateInitialValue(END_ROUND_TYPE, 0);
        $this->setGameStateInitialValue(LAST_CHANCE_CALLER, 0);
        $this->setGameStateInitialValue(STOP_CALLER, 0);
        $this->setGameStateInitialValue(BET_RESULT, 0);
        $this->setGameStateInitialValue(FORCE_TAKE_ONE, 0);
        $this->setGameStateInitialValue(LOBSTER_POWER, 0);
        
        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        $this->initStat('table', 'roundNumber', 0); 
        foreach(['table', 'player'] as $statType) {
            $this->initStat($statType, 'turnsNumber', 0);
            $this->initStat($statType, 'takeCardFromDeck', 0);
            $this->initStat($statType, 'takeFromDiscard', 0);
            $this->initStat($statType, 'playedDuoCards', 0);
            $this->initStat($statType, 'announce', 0);
            $this->initStat($statType, 'announceStop', 0);
            $this->initStat($statType, 'announceLastChance', 0);
            $this->initStat($statType, 'lastChanceBetWon', 0);
            $this->initStat($statType, 'lastChanceBetLost', 0);
            foreach([1,2,3,4] as $number) {
                $this->initStat($statType, 'cardsCollected'.$number, 0);
            }
            $this->initStat($statType, 'winWithMermaids', 0);
        }

        // setup the initial game situation here
        $this->setupCards();

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        // TODO TEMP card to test
        $this->debugSetup();

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas() {
        $result = [];
    
        $currentPlayerId = intval($this->getCurrentPlayerId());    // !! We must only return informations visible by this player !!
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score, player_no playerNo FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );

        

        $endRound = intval($this->getGameStateValue(END_ROUND_TYPE));
        $endCaller = 0;
        if ($endRound == LAST_CHANCE) {
            $endCaller = intval($this->getGameStateValue(LAST_CHANCE_CALLER));
        } else if ($endRound == STOP) {
            $endCaller = intval($this->getGameStateValue(STOP_CALLER));
        }

        foreach($result['players'] as $playerId => &$player) {
            $player['playerNo'] = intval($player['playerNo']);
            $handCards = $this->getPlayerCards($playerId, 'hand', false);
            $player['handCards'] = $playerId == $currentPlayerId ? $handCards : Card::onlyIds($handCards);
            $player['tableCards'] = $this->getPlayerCards($playerId, 'table', true);
            if ($playerId == $currentPlayerId) {
                $cardsPointsObj = $this->getCardsPoints($playerId);
                $player['cardsPoints'] = $cardsPointsObj->totalPoints;
                $player['detailledPoints'] = $cardsPointsObj->detailledPoints;
            }

            $betResult = intval($this->getGameStateValue(BET_RESULT));
            if ($endCaller == $playerId) {
                $player['endCall'] = [
                    'announcement' => $this->ANNOUNCEMENTS[$endRound],
                    'cardsPoints' => $this->getCardsPoints($playerId)->totalPoints,
                ];
                
                if (in_array($betResult, [1, 2])) {
                    $player['endCall']['betResult'] = $betResult == 2 ? clienttranslate('won') : clienttranslate('lost');
                }
            } else if (count($player['handCards']) == 0 && count($player['tableCards']) > 0) {
                $player['endRoundPoints'] = [
                    'cardsPoints' => $this->getCardsPoints($playerId)->totalPoints,
                ];
            }

            if ($endRound == LAST_CHANCE) {
                $playerScoreDetails = $this->getCardsPoints($playerId);
                if ($betResult == 2) { // won
                    if ($endCaller == $playerId) {
                        $player['scoringDetail'] = [
                            'cardsPoints' => $playerScoreDetails->totalPoints,
                            'colorBonus' => $playerScoreDetails->colorBonus,
                        ];
                    } else {
                        $player['scoringDetail'] = [
                            'cardsPoints' => null,
                            'colorBonus' => $playerScoreDetails->colorBonus,
                        ];
                    }
                } else if ($betResult == 1) { // lost
                    if ($endCaller == $playerId) {
                        $player['scoringDetail'] = [
                            'cardsPoints' => null,
                            'colorBonus' => $playerScoreDetails->colorBonus,
                        ];
                    } else {
                        $player['scoringDetail'] = [
                            'cardsPoints' => $playerScoreDetails->totalPoints,
                            'colorBonus' => null,
                        ];
                    }
                }
            } else if ($endRound == STOP) {
                $player['scoringDetail'] = [
                    'cardsPoints' => $this->getCardsPoints($playerId)->totalPoints,
                    'colorBonus' => null,
                ];
            } else if ($endRound == EMPTY_DECK) {
                $player['scoringDetail'] = [
                    'cardsPoints' => null,
                    'colorBonus' => null,
                ];
            }
        }

        $result['deckTopCard'] = $this->getDeckTopCard();
        $result['remainingCardsInDeck'] = $this->getRemainingCardsInDeck();
        foreach ([1, 2] as $number) {
            $result['discardTopCard'.$number] = $this->getCardFromDb($this->cards->getCardOnTop('discard'.$number));
            $result['remainingCardsInDiscard'.$number] = $this->getRemainingCardsInDiscard($number);
        }
  
        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression() {
        $maxScore = 100; // TODO
        $topScore = $this->getPlayerTopScore();

        return min(100, 100 * $topScore / $maxScore);
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn($state, $active_player) {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState("zombiePass");
                	break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb($from_version) {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        if ($from_version <= 2305281437) {
            // ! important ! Use DBPREFIX_<table_name> for all tables
            self::applyDbUpgradeToAllDB("ALTER TABLE DBPREFIX_card MODIFY COLUMN `card_location` varchar(25) NOT NULL");
        }

    }    
}
