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

use Bga\GameFramework\UserException;
use Bga\Games\Pixies\Objects\Card;
use Bga\Games\Pixies\States\KeepCard;
use Bga\Games\Pixies\States\NewRound;
use Bga\Games\Pixies\States\PlayCard;
use Bga\Games\Pixies\States\ChooseCard;

require_once('constants.inc.php');

class Game extends \Bga\GameFramework\Table {
    use DebugUtilTrait;

    public CardManager $cardManager;

	function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        
        $this->initGameStateLabels([
            LAST_TURN => LAST_TURN,
        ]);  

        $this->cardManager = new CardManager($this);
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
        $sql = "INSERT INTO player (player_id, player_color, player_name) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player ) {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".addslashes( $player['player_name'] )."')";
        }
        $sql .= implode(',', $values);
        $this->DbQuery( $sql );
        $this->reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        $this->reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        
        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        $this->bga->tableStats->init(['roundNumber', 'turnsNumber'], 0); 
        $this->bga->playerStats->init([
            'cardPlayedEmptySpaceVisible', 'cardPlayedEmptySpaceHidden', 'validatedCard', 
            'pointsValidatedCard', 'pointsSpirals', 'pointsLostCrosses', 'pointsColorZone', 'pointsFacedownCards',
        ], 0, updateTableStat: true);

        $this->cardManager->initDb();
        // setup the initial game situation here
        $this->cardManager->setup();

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        return NewRound::class;
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
    
        $isEndScore = $this->gamestate->getCurrentMainStateId() >= ST_END_SCORE;
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score, player_no playerNo FROM player ";
        $result['players'] = $this->getCollectionFromDb( $sql );

        foreach($result['players'] as $playerId => &$player) {
            $player['playerNo'] = intval($player['playerNo']);
            $player['cards'] = $this->cardManager->getCardsFromSpaces($playerId);
        }

        $result['remainingCardsInDeck'] = $this->cardManager->getRemainingCardsInDeck();
        $result['tableCards'] = $this->cardManager->getTableCards()->values();
        $result['roundNumber'] = $this->bga->tableStats->get('roundNumber');
        $result['roundResult'] = [];
        for($i = 1; $i <= 3; $i++) {
            $result['roundResult'][$i] = $this->getGlobalVariable(ROUND_RESULT.$i);
        }
        $result['lastTurn'] = !$isEndScore && boolval($this->getGameStateValue((string)LAST_TURN));
        $result['flowerPowerExpansion'] = $this->isFlowerPowerExpansion();
        $result['littleGiantsExpansion'] = $this->isLittleGiantsExpansion();
  
        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true.
    */
    function getGameProgression() {
        $roundNumber = $this->bga->tableStats->get('roundNumber');
        
        $playersIds = $this->getPlayersIds();
        $maxCards = 0;

        foreach ($playersIds as $playerId) {
            $playerCardCount = $this->cardManager->getPlayerCardCount($playerId);

            if ($playerCardCount > $maxCards) {
                $maxCards = $playerCardCount;
            }
        }
        $inRoundProgress = $maxCards / 9.0;

        return ($roundNumber - 1 + $inRoundProgress) * 100 / 3;
    }

    public function actChooseCard(int $id, int $activePlayerId): string { 
        $card = $this->cardManager->getTableCards()->find(fn($c) => $c->id === $id);
        if (!$card) {
            throw new UserException("You cannot choose this card");
        }
        
        $stateName = $this->gamestate->getCurrentMainState()->name; 
        $isChangeOfCard = $stateName === 'playCard' || $stateName === 'keepCard';
        if ($isChangeOfCard) {
            $this->gamestate->jumpToState(ChooseCard::class);
        }

        return $this->applyChooseCard($activePlayerId, $card);
    }

    function applyChooseCard(int $playerId, Card $card): string {
        $this->setGlobalVariable(SELECTED_CARD_ID, $card->id);

        [$row, $column] = Game::getRowColumnFromValue($card->value);
        $spaceCards = $this->cardManager->getCardsFromSpace($playerId, $row, $column);

        if (count($spaceCards) == 1 && $spaceCards[0]->value == $card->value) {
            return KeepCard::class;
        } else  {
            return PlayCard::class;
        }
    }

    function setGlobalVariable(string $name, mixed $obj) {
        /*if ($obj == null) {
            throw new \Error('Global Variable null');
        }*/
        $jsonObj = json_encode($obj);
        $this->DbQuery("INSERT INTO `global_variables`(`name`, `value`)  VALUES ('$name', '$jsonObj') ON DUPLICATE KEY UPDATE `value` = '$jsonObj'");
    }

    function getGlobalVariable(string $name, $asArray = null) {
        $json_obj = $this->getUniqueValueFromDB("SELECT `value` FROM `global_variables` where `name` = '$name'");
        if ($json_obj) {
            $object = json_decode($json_obj, $asArray);
            return $object;
        } else {
            return null;
        }
    }

    function deleteGlobalVariable(string $name) {
        $this->DbQuery("DELETE FROM `global_variables` where `name` = '$name'");
    }

    function deleteGlobalVariables(array $names) {
        $this->DbQuery("DELETE FROM `global_variables` where `name` in (".implode(',', array_map(fn($name) => "'$name'", $names)).")");
    }

    function getPlayersIds() {
        return array_keys($this->loadPlayersBasicInfos());
    }

    function isFlowerPowerExpansion(): bool {
        return $this->tableOptions->get(101) === 2;
    }

    function isLittleGiantsExpansion(): bool {
        return $this->tableOptions->get(102) === 1;
    }

    function getPlayerScore(int $playerId) {
        return $this->bga->playerScore->get($playerId);
    }

    function incPlayerScore(int $playerId, int $roundScore, $message = '', $args = []) {
        $this->bga->playerScore->inc($playerId, $roundScore, null);
            
        $this->notify->all('score', $message, [
            'playerId' => $playerId,
            'newScore' => $this->getPlayerScore($playerId),
            'incScore' => $roundScore,
            'round' => $this->bga->tableStats->get('roundNumber'),
        ] + $args);
    }
    
    public function decorateNotifArgs(string $message, array $args): array {
        if (isset($args['playerId']) && !isset($args['player_name']) && str_contains($message, '${player_name}')) {
            $args['player_name'] = $this->getPlayerNameById($args['playerId']);
        }

        if (isset($args['space']) && !isset($args['value']) && str_contains($message, '${value}')) {
            $args['value'] = $args['space'];
        }

        if ((isset($args['visibleCard']) || isset($args['card'])) && !isset($args['color']) && str_contains($message, '${color}')) {
            $card = $args['visibleCard'] ?? $args['card'];
            $args['color'] = $card->type > 10 ? [
                'log' => '${color1}/${color2}',
                'args' => [
                    'i18n' => ['color1', 'color2'],
                    'color1' => $this->getColorName(intdiv($card->type, 10)),
                    'color2' => $this->getColorName($card->type % 10),
                ]
            ] : $this->getColorName($card->type);
        }

        return $args;
    }

    private function getColorName(int $color): string {
        return match ($color) {
            0 => clienttranslate('Multicolor'),
            1 => clienttranslate('Blue'),
            2 => clienttranslate('Green'),
            3 => clienttranslate('Yellow'),
            4 => clienttranslate('Red'),
        };
    }

    public static function getRowFromValue(?int $value): int {
        return $value === null || $value < 1 || $value > 9 ? null : (intdiv($value - 1, 3) + 1);
    }

    public static function getColumnFromValue(?int $value): int {
        return $value === null || $value < 1 || $value > 9 ? null : ((($value - 1) % 3) + 1);
    }

    public static function getValueFromRowColumn(int $row, int $column): ?int {
        return $row < 1 || $row > 3 || $column < 1 || $column > 3 ? null : (($row-1) * 3 + $column);
    }
    public static function getRowColumnFromValue(?int $value): array {
        return [
            self::getRowFromValue($value),
            self::getColumnFromValue($value),
        ];
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
        
        if ($from_version <= 2609141115) {
            $sql = "ALTER TABLE `DBPREFIX_card` ADD `order` INT DEFAULT 0, MODIFY `card_location_arg` INT NULL";
            $this->applyDbUpgradeToAllDB($sql);

            $sql = "UPDATE `DBPREFIX_card` SET `order` = `card_location_arg` WHERE `card_location` = 'deck'";
            $this->applyDbUpgradeToAllDB($sql);

            $sql = "UPDATE `DBPREFIX_card` SET `card_location_arg` = NULL WHERE `card_location` = 'deck'";
            $this->applyDbUpgradeToAllDB($sql);
        }
        
        if ($from_version <= 2609151200) {
            $this->cardManager->cards->upgradeTableDbAddColumns(['row']);
            $this->cardManager->cards->upgradeTableDbAddColumns(['column']);
        }
    }    
}
