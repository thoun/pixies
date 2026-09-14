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

use Bga\Games\Pixies\States\KeepCard;
use Bga\Games\Pixies\States\NewRound;
use Bga\Games\Pixies\States\PlayCard;
use Bga\Games\Pixies\Objects\DetailledScore;
use Bga\Games\Pixies\Objects\Card;
use Bga\Games\Pixies\Objects\Coalition;

require_once('constants.inc.php');

class Game extends \Bga\GameFramework\Table {
    use DebugUtilTrait;

    public \Bga\GameFramework\Components\Deck $cards;

    public array $CARDS;
    public array $FLOWER_POWER_CARDS;
    public array $LITTLE_GIANTS_CARDS;

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

        $this->cards = $this->deckFactory->createDeck("card");
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
            $player['cards'] = $this->getCardsFromSpaces($playerId);
        }

        $result['remainingCardsInDeck'] = $this->getRemainingCardsInDeck();
        $result['tableCards'] = $this->getCardsFromDb($this->cards->getCardsInLocation('table'));
        $result['roundNumber'] = $this->bga->tableStats->get('roundNumber');
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
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true.
    */
    function getGameProgression() {
        $roundNumber = $this->bga->tableStats->get('roundNumber');
        
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

    function getPossibleSpacesForCard(array $playerCards, $card): array {
        $spaceCards = $playerCards[$card->value];
        $spaces = [];
        if (count($spaceCards) < 2) {
            $spaces[] = $card->value;
        } else {
            for ($i = 1; $i <= 9; $i++) {
                if ($i != $card->value && count($playerCards[$i]) == 0) {
                    $spaces[] = $i;
                }
            }
        }

        return $spaces;
    }
   
    function argPlayCard() {
        $playerId = intval($this->getActivePlayerId());

        $card = $this->getSelectedCard();
        $playerCards = $this->getCardsFromSpaces($playerId);

        $spaces = $this->getPossibleSpacesForCard($playerCards, $card);
    
        return [
            'selectedCard' => $card,
            'spaces' => $spaces,
        ];
    }

    public function actChooseCard(int $id, bool $autoplace): string {        
        $playerId = intval($this->getActivePlayerId());

        $card = $this->getCardFromDb($this->cards->getCard($id));
        if ($card->location != 'table') {
            throw new \BgaUserException("You cannot choose this card");
        }
        
        $stateName = $this->gamestate->getCurrentMainState()->name; 
        $isChangeOfCard = $stateName === 'playCard' || $stateName === 'keepCard';
        if ($isChangeOfCard) {
            $this->gamestate->nextState('cancel');
        }

        $newState = $this->applyChooseCard($playerId, $card);
        $this->gamestate->jumpToState($newState);

        if ($autoplace && $this->gamestate->getCurrentMainState()->name === 'playCard') {
            $spaces = $this->argPlayCard()['spaces'];
            if (count($spaces) == 1) {
                $this->applyPlayCard($playerId, $spaces[0]);
            }
        }

        return $newState;
    }

    function applyChooseCard(int $playerId, Card $card): string {
        $this->setGlobalVariable(SELECTED_CARD_ID, $card->id);

        $spaceCards = $this->getCardsFromSpace($playerId, $card->value);

        if (count($spaceCards) == 1 && $spaceCards[0]->value == $card->value) {
            return KeepCard::class;
        } else  {
            return PlayCard::class;
        }
    }

    public function applyPlayCard(int $playerId, int $space) {
        $card = $this->getSelectedCard();

        $count = intval($this->cards->countCardInLocation("player-$playerId-$space"));
        $this->cards->moveCard($card->id, "player-$playerId-$space", $count);
        $card->locationArg = $count;

        $statName = $space == $card->value ? 'cardPlayedEmptySpaceVisible' : 'cardPlayedEmptySpaceHidden';
        $this->incStat(1, $statName);
        $this->incStat(1, $statName, $playerId);

        $this->notify->all('playCard', clienttranslate('${player_name} plays a ${color} card on space ${value}'), [
            'playerId' => $playerId,
            'card' => $space == $card->value ? $card : Card::onlyId($card),
            'space' => $space,
            'visibleCard' => $card, // only used for logs
        ]);

        if (!boolval($this->getGameStateValue((string)\LAST_TURN)) && $this->getPlayerCardCount($playerId) >= 9) {
            $this->setGameStateValue((string)\LAST_TURN, 1);

            $this->notify->all('lastTurn', clienttranslate('${player_name} has filled all 9 of their spaces, triggering the end of the round!'), [
                'playerId' => $playerId,
            ]);
        }

        $this->gamestate->nextState('next');
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

    function getCardFromDb(?array $dbCard) {
        if ($dbCard == null) {
            return null;
        }

        $CARDS = $this->CARDS + $this->FLOWER_POWER_CARDS;
        for ($i = 0; $i <= 4; $i++) {
            $CARDS[$i] += $this->LITTLE_GIANTS_CARDS[$i];
        }  
        return new Card($dbCard,  $CARDS);
    }

    /**
     * @return Card[]
     */
    function getCardsFromDb(array $dbCards): array {
        return array_map(fn($dbCard) => $this->getCardFromDb($dbCard), array_values($dbCards));
    }

    /**
     * @return Card[]
     */
    function getCardsFromSpace(int $playerId, int $value): array {
        return $this->getCardsFromDb($this->cards->getCardsInLocation("player-$playerId-$value", null, 'location_arg'));
    }

    /**
     * @return array<int,Card[]>
     */    
    function getCardsFromSpaces(int $playerId): array {
        $spaces = [];

        for ($i = 1; $i <= 9; $i++) {
            $spaces[$i] = $this->getCardsFromSpace($playerId, $i);
            if (count($spaces[$i]) == 2 || (count($spaces[$i]) == 1 && $spaces[$i][0]->value != $i)) {
                $spaces[$i][0] = Card::onlyId($spaces[$i][0]);
            }
        }

        return $spaces;
    }

    function countFacedownCards(array $spaces): int {
        $result = 0;

        foreach ($spaces as $space) {
            if (count($space) == 1 && $space[0]->value === null) {
                $result++;
            }
        }

        return $result;
    }

    function getSelectedCard() {
        return $this->getCardFromDb($this->cards->getCard($this->getGlobalVariable(SELECTED_CARD_ID)));
    }

    function setupCards() {
        $cardsToGenerate = [];
        $CARDS = $this->CARDS;
        if ($this->isFlowerPowerExpansion()) {
            $CARDS += $this->FLOWER_POWER_CARDS;
        }
        if ($this->isLittleGiantsExpansion()) {
            for ($i = 0; $i <= 4; $i++) {
                $CARDS[$i] += $this->LITTLE_GIANTS_CARDS[$i];
            }            
        }
        foreach ($CARDS as $type => $cardsTypes) {
            foreach ($cardsTypes as $index => $cardType) {
                $cardsToGenerate[] = [ 'type' => $type, 'type_arg' => $index, 'nbr' => 1 ];
            }
        }
        $this->cards->createCards($cardsToGenerate, 'deck');
        $this->cards->shuffle('deck');
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

    function getRemainingCardsInDeck() {
        return intval($this->cards->countCardInLocation('deck'));
    }


    /**
     * @param array<int,?Card> $validatedCards
     */
    function getColorZoneSize(array $validatedCards, Coalition $coalition, int $currentRow, int $currentColumn): void {
        // we check we don't count twice the same space
        if (array_search([$currentRow, $currentColumn], $coalition->alreadyCounted) !== false) {
            return;
        }

        $coalition->size++;
        $coalition->alreadyCounted = array_merge($coalition->alreadyCounted, [[$currentRow, $currentColumn]]);

        $neighbourValues = [];
        foreach([[0, -1], [0, 1], [-1, 0], [1, 0]] as $neighbourShift) {
            $iRow = $currentRow + $neighbourShift[0];
            $iColumn = $currentColumn + $neighbourShift[1];
            if ($iRow < 0 || $iRow > 3 || $iColumn < 0 || $iColumn > 3) {
                continue;
            }                
            $neighbourValues[] = [$iRow, $iColumn];
        }


        // we only take cards having same color
        $filteredNeigbours = array_filter($neighbourValues, fn($neighbour) =>
            array_any($validatedCards, fn($validatedCard) => $validatedCard !== null && $validatedCard->getRow() === $neighbour[0] && $validatedCard->getColumn() === $neighbour[1] && in_array($coalition->color, $validatedCard->colors))
        );

        foreach ($filteredNeigbours as $filteredNeigbour) {
            $this->getColorZoneSize($validatedCards, $coalition, $filteredNeigbour[0], $filteredNeigbour[1]);
        }
    }

    /**
     * @param array<int,?Card> $visibleCards
     */
    function getLargestColorZone(array $visibleCards): int {
        $topCoalition = null;

        for ($space = 1; $space <= 9; $space++) {
            $cardInSpace = $visibleCards[$space];

            if ($cardInSpace) {
                foreach ($cardInSpace->colors as $color) {
                    $coalition = new Coalition($cardInSpace->getRow(), $cardInSpace->getColumn(), $color);
                    $this->getColorZoneSize($visibleCards, $coalition, $cardInSpace->getRow(), $cardInSpace->getColumn());
                    
                    if (!$topCoalition || $coalition->size > $topCoalition->size) {
                        $topCoalition = $coalition;
                    }
                }
            }
        }

        return $topCoalition->size;
    }

    function getDetailledScore(array $spaces, int $roundNumber, bool $isFlowerPowerExpansion): DetailledScore {
        $detailledScore = new DetailledScore();
        /** @var array<int,?Card> */
        $validatedCards = array_map(fn($space) => count($space) == 2 ? $space[1] : null, $spaces);
        /** @var array<int,?Card> */
        $visibleCards = array_map(fn($space) => count($space) == 2 ? $space[1] : (count($space) == 1 && $space[0]->value !== null ? $space[0] : null), $spaces);
        $facedownCardsCount = $isFlowerPowerExpansion ? $this->countFacedownCards($spaces) : 0;
        $colorsCounts = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
        ];
        foreach ($visibleCards as $visibleCard) {
            if ($visibleCard?->type !== null) {
                foreach ($visibleCard->colors as $color) {
                    $colorsCounts[$color]++;
                }
            }
        }

        $detailledScore->validatedCardPoints = 0;
        $spiralsPoints = 0;
        $crossesPoints = 0;
        $largestColorZone = 0;
        $detailledScore->largestColorZonePoints = 0;
        $facedownCardsPoints = $facedownCardsCount * 5;

        foreach ($validatedCards as $space => $card) {
            if ($card) {
                $detailledScore->validatedCardPoints += $space;
            }
        }

        foreach ($visibleCards as $space => $card) {
            if ($card) {
                if ($card->spirals != 0) {
                    if ($card->spirals == -1) {
                        $spiralsPoints += $colorsCounts[$card->colors[0]]; // spiral -1 is always of the single color of the card
                    } else {
                        $spiralsPoints += $card->spirals;
                    }
                }
                if ($card->crosses != 0) {
                    if ($card->crosses < 0) {
                        $crossesPoints += $colorsCounts[-$card->crosses]; // X per color is coded as negative cross
                    } else {
                        $crossesPoints += $card->crosses;
                    }
                }
                if ($card->spiralsPerFacedownCard > 0) {
                    $facedownCardsPoints += $card->spiralsPerFacedownCard * $facedownCardsCount;
                }
                $colorZone = $this->getLargestColorZone($visibleCards);
                if ($colorZone > $largestColorZone) {
                    $largestColorZone = $colorZone;
                }
            }
        }

        // largest zone must be 2 cards min to score
        if ($largestColorZone == 1) {
            $largestColorZone = 0;
        }

        $detailledScore->spiralsAndCrossesPoints = $spiralsPoints - $crossesPoints;
        $detailledScore->largestColorZonePoints = $largestColorZone * ($roundNumber + 1);
        if ($isFlowerPowerExpansion) {
            $detailledScore->facedownCardsPoints = $facedownCardsPoints;
        }

        $detailledScore->points = 
            $detailledScore->validatedCardPoints + 
            $detailledScore->spiralsAndCrossesPoints + 
            $detailledScore->largestColorZonePoints;
        if ($isFlowerPowerExpansion) {
            $detailledScore->points += $detailledScore->facedownCardsPoints;
        }
        return $detailledScore;
    }
    
    function scoreRound() {
        $roundNumber = $this->bga->tableStats->get('roundNumber');
        $playersIds = $this->getPlayersIds();
        $result = [];
        $isFlowerPowerExpansion = $this->isFlowerPowerExpansion();

        foreach ($playersIds as $playerId) {
            $playerCards = $this->getCardsFromSpaces($playerId);
            $detailledScore = $this->getDetailledScore($playerCards, $roundNumber, $isFlowerPowerExpansion);
            $result[$playerId] = $detailledScore;  
        
            $this->incStat($detailledScore->validatedCardPoints, 'pointsValidatedCard');
            $this->incStat($detailledScore->validatedCardPoints, 'pointsValidatedCard', $playerId);
            $this->incStat($detailledScore->spiralsPoints, 'pointsSpirals');
            $this->incStat($detailledScore->spiralsPoints, 'pointsSpirals', $playerId);
            $this->incStat($detailledScore->crossesPoints, 'pointsLostCrosses');
            $this->incStat($detailledScore->crossesPoints, 'pointsLostCrosses', $playerId);
            $this->incStat($detailledScore->largestColorZonePoints, 'pointsColorZone');
            $this->incStat($detailledScore->largestColorZonePoints, 'pointsColorZone', $playerId);
            $this->incStat($detailledScore->facedownCardsPoints, 'pointsFacedownCards');
            $this->incStat($detailledScore->facedownCardsPoints, 'pointsFacedownCards', $playerId);
        }

        return $result;
    }

    function getPlayerCardCount(int $playerId) {
        $playerCards = $this->getCardsFromSpaces($playerId);
        $playerCardCount = array_reduce(array_map(fn($cards) => count($cards) > 0 ? 1 : 0, $playerCards), fn($a, $b) => $a + $b, 0);

        return $playerCardCount;
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
