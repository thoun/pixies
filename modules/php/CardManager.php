<?php

declare(strict_types=1);

namespace Bga\Games\Pixies;

use Bga\GameFramework\Components\ItemManager\ItemLocation;
use Bga\GameFramework\Components\ItemManager\ItemManager;
use Bga\GameFramework\Helpers\Collection;
use Bga\Games\Pixies\Objects\Card;
use Bga\Games\Pixies\Objects\CardType;
use Bga\Games\Pixies\Objects\Coalition;
use Bga\Games\Pixies\Objects\DetailledScore;

class CardManager
{
    /** @var ItemManager<Card> */
    public ItemManager $cards;

    public static array $CARDS = [];
    public static array $FLOWER_POWER_CARDS = [];
    public static array $LITTLE_GIANTS_CARDS = [];

    public function __construct(private Game $game)
    {
        self::$CARDS = [
            0 => [ // all colors
                1 => new CardType(2),
                2 => new CardType(3),
                3 => new CardType(4),
                4 => new CardType(6, crosses: 1),
                5 => new CardType(7, crosses: 1),
                6 => new CardType(8, crosses: 1),
            ],

            1 => [ // blue
                1 => new CardType(1, 6),
                2 => new CardType(2, 4),
                3 => new CardType(3, 3),
                4 => new CardType(3, 1),
                5 => new CardType(4, 1),
                6 => new CardType(4, -1),
                7 => new CardType(4),
                8 => new CardType(5),
                9 => new CardType(5, crosses: 2),
                10 => new CardType(5, -1),
                11 => new CardType(6, 1),
                12 => new CardType(6, crosses: 1),
                13 => new CardType(7, crosses: 3),
                14 => new CardType(8, crosses: 3),
                15 => new CardType(9, crosses: 6),
                16 => new CardType(9, crosses: 1),
            ],

            2 => [ // green
                1 => new CardType(1, 5),
                2 => new CardType(2, 3),
                3 => new CardType(3, 2),
                4 => new CardType(3, -1),
                5 => new CardType(4, 4),
                6 => new CardType(4, crosses: 1),
                7 => new CardType(5),
                8 => new CardType(5, crosses: 1),
                9 => new CardType(5, -1),
                10 => new CardType(6, crosses: 4),
                11 => new CardType(6, crosses: 1),
                12 => new CardType(6, 1),
                13 => new CardType(7, crosses: 2),
                14 => new CardType(7),
                15 => new CardType(8, crosses: 2),
                16 => new CardType(9, crosses: 4),
            ],

            3 => [ // yellow
                1 => new CardType(1, 4),
                2 => new CardType(2, 2),
                3 => new CardType(2, -1),
                4 => new CardType(3, 5),
                5 => new CardType(3),
                6 => new CardType(4, 3),
                7 => new CardType(4, crosses: 1),
                8 => new CardType(5, crosses: 2),
                9 => new CardType(5),
                10 => new CardType(5, -1),
                11 => new CardType(6, crosses: 3),
                12 => new CardType(6),
                13 => new CardType(7, crosses: 5),
                14 => new CardType(7, 1),
                15 => new CardType(8, crosses: 1),
                16 => new CardType(9, crosses: 2),
            ],

            4 => [ // red
                1 => new CardType(1, 3),
                2 => new CardType(1, -1),
                3 => new CardType(2, 5),
                4 => new CardType(3, 4),
                5 => new CardType(4, 2),
                6 => new CardType(4),
                7 => new CardType(5, crosses: 1),
                8 => new CardType(5, -1),
                9 => new CardType(5),
                10 => new CardType(6, crosses: 2),
                11 => new CardType(6),
                12 => new CardType(7, crosses: 4),
                13 => new CardType(7, crosses: 1),
                14 => new CardType(8, crosses: 5),
                15 => new CardType(8),
                16 => new CardType(9),
            ],
        ];

        self::$FLOWER_POWER_CARDS = [
            12 => [ // Blue and Green
                1 => new CardType(8, spiralsPerFacedownCard: 1, crosses: 1),
                2 => new CardType(2, spirals: 6, crosses: -3),
            ],
            13 => [ // Blue and Yellow
                1 => new CardType(5, spiralsPerFacedownCard: 2),
                2 => new CardType(1, spirals: 7, crosses: -4),
                3 => new CardType(8, spirals: 1, crosses: -4),
            ],
            14 => [ // Blue and Red
                1 => new CardType(2, spiralsPerFacedownCard: 3, crosses: 1),
                2 => new CardType(7, spirals: 2, crosses: -2),
            ],
            23 => [ // Green and Yellow
                1 => new CardType(1, spiralsPerFacedownCard: 3),
                2 => new CardType(9, crosses: -1),
            ],
            24 => [ // Green and Red
                1 => new CardType(5, spiralsPerFacedownCard: 2, crosses: 1),
                2 => new CardType(3, spirals: 5, crosses: -1),
                3 => new CardType(6, spirals: 3, crosses: -3),
            ],
            34 => [ // Yellow and Red
                1 => new CardType(9, spiralsPerFacedownCard: 1, crosses: 2),
                2 => new CardType(4, spirals: 4, crosses: -2),
            ],
        ];

        self::$LITTLE_GIANTS_CARDS = [
            0 => [ // all colors
                107 => new CardType(columnEffect: 7),
                114 => new CardType(rowEffect: 7),
            ],
            1 => [ // blue
                104 => new CardType(columnEffect: 4),
                110 => new CardType(rowEffect: 3),
                111 => new CardType(rowEffect: 4),
            ],
            2 => [ // green
                101 => new CardType(columnEffect: 1),
                102 => new CardType(columnEffect: 2),
                113 => new CardType(rowEffect: 6),
            ],
            3 => [ // yellow
                103 => new CardType(columnEffect: 3),
                108 => new CardType(rowEffect: 1),
                109 => new CardType(rowEffect: 2),
            ],
            4 => [ // red
                105 => new CardType(columnEffect: 5),
                106 => new CardType(columnEffect: 6),
                112 => new CardType(rowEffect: 5),
            ],
        ];

        $this->cards = $game->bga->itemManagerFactory->createItemManager(
            Card::class,
            locations: [
                new ItemLocation('deck'),
                new ItemLocation('table'),
                new ItemLocation('player-*'),
            ],
        );
    }

    public function initDb(): void
    {
        $this->cards->initDb();
    }

    public function setup(): void
    {

        $cardsToGenerate = [];
        $CARDS = self::$CARDS;
        if ($this->game->isFlowerPowerExpansion()) {
            $CARDS += self::$FLOWER_POWER_CARDS;
        }
        if ($this->game->isLittleGiantsExpansion()) {
            for ($i = 0; $i <= 4; $i++) {
                $CARDS[$i] += self::$LITTLE_GIANTS_CARDS[$i];
            }            
        }
        foreach ($CARDS as $type => $cardsTypes) {
            foreach ($cardsTypes as $index => $cardType) {
                $cardsToGenerate[] = [ 'location' => 'deck', 'type' => $type, 'index' => $index ];
            }
        }
        $this->cards->createItems($cardsToGenerate);
        $this->cards->shuffle(['deck']);
    }

    /** @return Collection<Card> */
    public function getTableCards(): Collection
    {
        return $this->cards->getItemsInLocation(['table']);
    }

    public function getSelectedCard(): Card {
        return $this->cards->getItemById($this->game->getGlobalVariable(\SELECTED_CARD_ID));
    }

    public function pickNewTableCards(): void
    {
        $playerCount = $this->game->getPlayerCount();
        $cardCount = $playerCount === 2 ? 4 : $playerCount;

        $cards = $this->cards->pickItems($cardCount, ['deck'], ['table']);

        $this->game->bga->notify->all('newTurn', '', [
            'cards' => $cards->values(),
        ]);
    }

    public function getRemainingCardsInDeck(): int {
        return $this->cards->countItemsInLocation(['deck']);
    }

    /**
     * @return Card[]
     */
    public function getCardsFromSpace(int $playerId, int $row, int $column): array {
        $value = Game::getValueFromRowColumn($row, $column);
        return $this->cards->getItemsInLocation(["player-$playerId-$value"])->sort(fn($a, $b) => $a->locationArg <=> $b->locationArg)->values();
    }

    /**
     * @return array<string,Card[]>
     */    
    public function getCardsFromSpaces(int $playerId): array {
        $spaces = [];

        for ($row = 1; $row <= 3; $row++) {
            for ($column = 1; $column <= 3; $column++) {
                $coordinate = $row.'-'.$column;
                $i = Game::getValueFromRowColumn($row, $column);
                $spaces[$coordinate] = $this->getCardsFromSpace($playerId, $row, $column);
                if (count($spaces[$coordinate]) == 2 || (count($spaces[$coordinate]) == 1 && $spaces[$coordinate][0]->value != $i)) {
                    $spaces[$coordinate][0] = Card::onlyId($spaces[$coordinate][0]);
                }
            }
        }

        return $spaces;
    }

    /**
     * @param array<string,Card[]> $playerCards
     * @param Card $card
     * @return string[]
     */
    public function getPossibleSpacesForCard(array $playerCards, Card $card): array {
        [$cardRow, $cardColumn] = Game::getRowColumnFromValue($card->value);
        $spaceCards = $playerCards[$cardRow.'-'.$cardColumn];
        $spaces = [];
        if (count($spaceCards) < 2) {
            // can play the card on the value
            $spaces[] = $cardRow.'-'.$cardColumn;
            return $spaces;
        }
         
        // must place on any other empty spot
        for ($row = 1; $row <= 3; $row++) {
            for ($column = 1; $column <= 3; $column++) {
                if ($card->value != Game::getValueFromRowColumn($row, $column) && count($playerCards[$row.'-'.$column]) == 0) {
                    $spaces[] = $row.'-'.$column;
                }
            }
        }
        return $spaces;
    }

    /**
     * @param array<string,Card[]> $spaces
     */
    private function countFacedownCards(array $spaces): int {
        $result = 0;

        foreach ($spaces as $space) {
            if (count($space) == 1 && $space[0]->value === null) {
                $result++;
            }
        }

        return $result;
    }

    /**
     * @param array<string,Card[]> $spaces
     */
    public function getDetailledScore(array $spaces, int $roundNumber, bool $isFlowerPowerExpansion): DetailledScore {
        $detailledScore = new DetailledScore();
        /** @var array<string,?Card> */
        $validatedCards = array_map(fn($space) => count($space) == 2 ? $space[1] : null, $spaces);
        /** @var array<string,?Card> */
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

        foreach ($validatedCards as $coordinateStr => $card) {
            if ($card) {
                $coordinate = array_map(fn($n) => intval($n), explode('-', $coordinateStr));
                $value = Game::getValueFromRowColumn($coordinate[0], $coordinate[1]);
                $detailledScore->validatedCardPoints += $value;
            }
        }

        foreach ($visibleCards as $card) {
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

    /**
     * @param array<string,?Card> $visibleCards
     */
    private function getLargestColorZone(array $visibleCards): int {
        $topCoalition = null;


        for ($row = 1; $row <= 3; $row++) {
            for ($column = 1; $column <= 3; $column++) {
                $cardInSpace = $visibleCards[$row.'-'.$column];

                if ($cardInSpace) {
                    foreach ($cardInSpace->colors as $color) {
                        $coalition = new Coalition($row, $column, $color);
                        $this->getColorZoneSize($visibleCards, $coalition, $row, $column);
                        
                        if (!$topCoalition || $coalition->size > $topCoalition->size) {
                            $topCoalition = $coalition;
                        }
                    }
                }
            }
        }

        return $topCoalition->size;
    }


    /**
     * @param array<string,?Card> $validatedCards
     */
    private function getColorZoneSize(array $validatedCards, Coalition $coalition, int $currentRow, int $currentColumn): void {
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

    public function getPlayerCardCount(int $playerId): int {
        $playerCards = $this->getCardsFromSpaces($playerId);
        $playerCardCount = array_reduce(array_map(fn($cards) => count($cards) > 0 ? 1 : 0, $playerCards), fn($a, $b) => $a + $b, 0);

        return $playerCardCount;
    }
    
    public function playCard(int $playerId, Card $card, int $row, int $column): void {
        $count = count($this->getCardsFromSpace($playerId, $row, $column));
        $card->location = "player-$playerId-".Game::getValueFromRowColumn($row, $column);
        $card->locationArg = $count;
        $this->cards->moveItem($card->id, [$card->location, $card->locationArg]);
        $card->row = $row;
        $card->column = $column;
    }
}