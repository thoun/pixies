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
declare(strict_types=1);

namespace Bga\Games\Pixies;

require_once('constants.inc.php');
require_once('utils.php');
require_once('actions.php');
require_once('states.php');
require_once('args.php');
require_once('debug-util.php');

class Game extends \Bga\GameFramework\Table {
    use \UtilTrait;
    use \ActionTrait;
    use \StateTrait;
    use \ArgsTrait;
    use \DebugUtilTrait;

    public \Bga\GameFramework\Components\Deck $cards;

	function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        include 'material.inc.php';
        
        $this->initGameStateLabels([
            LAST_TURN => LAST_TURN,
        ]);  

        $this->cards = $this->getNew("module.common.deck");
        $this->cards->init("card");        
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
        $gameinfos = $this->getGameinfos();
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
        $this->DbQuery( $sql );
        $this->reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        $this->reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        
        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        $this->initStat('table', 'roundNumber', 0); 
        $this->initStat('table', 'turnsNumber', 0);
        foreach(['table', 'player'] as $statType) {
            foreach([
                'cardPlayedEmptySpaceVisible', 'cardPlayedEmptySpaceHidden', 'validatedCard', 
                'pointsValidatedCard', 'pointsSpirals', 'pointsLostCrosses', 'pointsColorZone', 'pointsFacedownCards',
            ] as $statName) {
                $this->initStat($statType, $statName, 0);
            }
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
    protected function getAllDatas(): array {
        $result = [];
    
        $isEndScore = intval($this->gamestate->state_id()) >= ST_END_SCORE;
        $currentPlayerId = intval($this->getCurrentPlayerId());    // !! We must only return informations visible by this player !!
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score, player_no playerNo FROM player ";
        $result['players'] = $this->getCollectionFromDb( $sql );

        foreach($result['players'] as $playerId => &$player) {
            $player['playerNo'] = intval($player['playerNo']);
            $player['cards'] = $this->getCardsFromSpaces($playerId);
        }

        $result['remainingCardsInDeck'] = $this->getRemainingCardsInDeck();
        $result['tableCards'] = $this->getCardsFromDb($this->cards->getCardsInLocation('table'));
        $result['roundNumber'] = intval($this->getStat('roundNumber'));
        $result['roundResult'] = [];
        for($i = 1; $i <= 3; $i++) {
            $result['roundResult'][$i] = $this->getGlobalVariable(ROUND_RESULT.$i);
        }
        $result['lastTurn'] = !$isEndScore && boolval($this->getGameStateValue((string)LAST_TURN));
        $result['flowerPowerExpansion'] = $this->isFlowerPowerExpansion();
  
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
        $roundNumber = intval($this->getStat('roundNumber'));
        
        $playersIds = $this->getPlayersIds();
        $maxCards = 0;

        foreach ($playersIds as $playerId) {
            $playerCardCount = $this->getPlayerCardCount($playerId);

            if ($playerCardCount > $maxCards) {
                $maxCards = $playerCardCount;
            }
        }
        $inRoundProgress = $maxCards / 9.0;

        return ($roundNumber - 1 + $inRoundProgress) * 100 / 3;
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

    private function getPointsFromChooseCardChoice(array $playerCards, int $roundNumber, bool $isFlowerPowerExpansion, $card): int {
        if (count($playerCards[$card->value]) == 1 && $playerCards[$card->value][0]->value == $card->value) {
            // it will be a keep card

            $possibleAnswerPoints = array_map(
            fn($choice) => $this->getPointsFromKeepCardChoice($playerCards, $roundNumber, $isFlowerPowerExpansion, $card, $choice),
                [0, 1]
            );
            $maxPoints = max($possibleAnswerPoints);
            
            return $maxPoints;
        } else {
            // it will be play card
            $possibleSpaces = $this->getPossibleSpacesForCard($playerCards, $card);

            $possibleAnswerPoints = [];
            foreach ($possibleSpaces as $choice) {
                $possibleAnswerPoints[$choice] = $this->getPointsFromPlayCardChoice($playerCards, $roundNumber, $isFlowerPowerExpansion, $card, $choice);
            }

            $maxPoints = max($possibleAnswerPoints);
            
            return $maxPoints;
        }
    }

    function zombieTurn_chooseCard(int $playerId): void {
        $roundNumber = intval($this->getStat('roundNumber'));
        $isFlowerPowerExpansion = $this->isFlowerPowerExpansion();
        $playerCards = $this->getCardsFromSpaces($playerId);

        $tableCards = $this->getCardsFromDb($this->cards->getCardsInLocation('table'));

        $possibleAnswerPoints = [];
        foreach ($tableCards as $choice => $card) {
            $possibleAnswerPoints[$choice] = $this->getPointsFromChooseCardChoice($playerCards, $roundNumber, $isFlowerPowerExpansion, $card);
        }

        $maxPoints = max($possibleAnswerPoints);
        $maxPointsAnswers = array_keys($possibleAnswerPoints, $maxPoints);
        $zombieChoice = $maxPointsAnswers[bga_rand(0, count($maxPointsAnswers) - 1)];

        $this->applyChooseCard($playerId, $tableCards[$zombieChoice]);
    }

    private function getPointsFromPlayCardChoice(array $playerCards, int $roundNumber, bool $isFlowerPowerExpansion, $card, int $choice): int {
        $playerCardsCopy = $playerCards;
        $playerCardsCopy[$choice] = array_merge($playerCardsCopy[$choice], [$card]);
        return $this->getDetailledScore($playerCardsCopy, $roundNumber, $isFlowerPowerExpansion)->points;
    }

    function zombieTurn_playCard(int $playerId): void {
        $roundNumber = intval($this->getStat('roundNumber'));
        $isFlowerPowerExpansion = $this->isFlowerPowerExpansion();
        $playerCards = $this->getCardsFromSpaces($playerId);

        $card = $this->getSelectedCard();

        $possibleSpaces = $this->getPossibleSpacesForCard($playerCards, $card);

        $possibleAnswerPoints = [];
        foreach ($possibleSpaces as $choice) {
            $possibleAnswerPoints[$choice] = $this->getPointsFromPlayCardChoice($playerCards, $roundNumber, $isFlowerPowerExpansion, $card, $choice);
        }

        $maxPoints = max($possibleAnswerPoints);
        $maxPointsAnswers = array_keys($possibleAnswerPoints, $maxPoints);
        $zombieChoice = $maxPointsAnswers[bga_rand(0, count($maxPointsAnswers) - 1)];

        $this->applyPlayCard($playerId, $zombieChoice);
    }

    private function getPointsFromKeepCardChoice(array $playerCards, int $roundNumber, bool $isFlowerPowerExpansion, $card, int $choice): int {
        $space = $card->value;
        $playerCardsCopy = $playerCards;
        $playerCardsCopy[$space] = $choice === 0 ? [\Card::onlyId($card), $playerCards[$space][0]] : [\Card::onlyId($playerCards[$space][0]), $card];
        return $this->getDetailledScore($playerCardsCopy, $roundNumber, $isFlowerPowerExpansion)->points;
    }

    function zombieTurn_keepCard(int $playerId): void {
        $roundNumber = intval($this->getStat('roundNumber'));
        $isFlowerPowerExpansion = $this->isFlowerPowerExpansion();
        $playerCards = $this->getCardsFromSpaces($playerId);

        $card = $this->getSelectedCard();

        $possibleAnswerPoints = array_map(
            fn($choice) => $this->getPointsFromKeepCardChoice($playerCards, $roundNumber, $isFlowerPowerExpansion, $card, $choice),
            [0, 1]
        );

        $maxPoints = max($possibleAnswerPoints);
        $maxPointsAnswers = array_keys($possibleAnswerPoints, $maxPoints);
        $zombieChoice = $maxPointsAnswers[bga_rand(0, count($maxPointsAnswers) - 1)];

        $this->applyKeepCard($playerId, $zombieChoice);
    }

    function zombieTurn($state, $active_player): void {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                case 'chooseCard':
                    $this->zombieTurn_chooseCard((int)$active_player);
                    break;
                case 'playCard':
                    $this->zombieTurn_playCard((int)$active_player);
                    break;
                case 'keepCard':
                    $this->zombieTurn_keepCard((int)$active_player);
                    break;
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

        throw new \feException( "Zombie mode not supported at this game state: ".$statename );
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
            $this->applyDbUpgradeToAllDB("ALTER TABLE DBPREFIX_card MODIFY COLUMN `card_location` varchar(25) NOT NULL");
        }

    }    
}
