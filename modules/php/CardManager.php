<?php

declare(strict_types=1);

namespace Bga\Games\Pixies;

use Bga\GameFramework\Components\ItemManager\ItemLocation;
use Bga\GameFramework\Components\ItemManager\ItemManager;
use Bga\GameFramework\Helpers\Collection;
use Bga\GameFramework\SystemException;
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
                107 => new CardType(columnEffect: 53),
                114 => new CardType(rowEffect: 52),
            ],
            1 => [ // blue
                104 => new CardType(columnEffect: 13),
                110 => new CardType(rowEffect: 31),
                111 => new CardType(rowEffect: 40),
            ],
            2 => [ // green
                101 => new CardType(columnEffect: 13),
                102 => new CardType(columnEffect: 24),
                113 => new CardType(rowEffect: 32),
            ],
            3 => [ // yellow
                103 => new CardType(columnEffect: 13),
                108 => new CardType(rowEffect: 33),
                109 => new CardType(rowEffect: 40),
            ],
            4 => [ // red
                105 => new CardType(columnEffect: 13),
                106 => new CardType(columnEffect: 24),
                112 => new CardType(rowEffect: 34),
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

    /** @return Collection<Card> */
    public function getPlayerCards(int $playerId): Collection
    {
        $cards = $this->cards->getItemsInLocation(["player-$playerId-%"], sortByField: 'locationArg');
        foreach ($cards as $id => $card) {
            if ($card->locationArg === 0 && !$card->flipped) {
                $hasCardOver = $cards->count(fn($c) => $c->row === $card->row && $c->column === $card->column && $c->locationArg > 0);
                if ($card->value !== null && !isset($card->row)) {
                   [$cardRow, $cardColumn] = Game::getRowColumnFromValue(intval(explode('-', $card->location)[2]));
                   $card->row = $cardRow;
                   $card->column = $cardColumn;
                }
                if ($hasCardOver) {
                    $card->flipped = true;
                } else if ($card->value !== null && $card->value !== Game::getValueFromRowColumn($card->row, $card->column)) {
                    $card->flipped = true;
                } else if ($card->value === null && ($card->row > 0 && $card->column > 0)) {
                    $card->flipped = true;
                }
                if ($card->flipped) {
                    $cards[$id] = Card::onlyId($card);
                }
            }
        }
        return $cards;
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
     * @return array<string,Card[]>
     */    
    public function getCardsFromSpaces(int $playerId): array {
        $spaces = [];

        $min = $this->game->isLittleGiantsExpansion() ? 0 : 1;
        for ($row = $min; $row <= 3; $row++) {
            for ($column = $min; $column <= 3; $column++) {
                if ($row === 0 && $column === 0) {
                    continue;
                }
                $coordinate = $row.'-'.$column;
                $value = Game::getValueFromRowColumn($row, $column);
                $spaces[$coordinate] = $this->cards->getItemsInLocation(["player-$playerId-$value"])->sort(fn($a, $b) => $a->locationArg <=> $b->locationArg)->values();
                if (count($spaces[$coordinate]) == 2 || (count($spaces[$coordinate]) == 1 && $spaces[$coordinate][0]->value != $value)) {
                    $spaces[$coordinate][0] = Card::onlyId($spaces[$coordinate][0]);
                }
            }
        }

        return $spaces;
    }

    /**
     * @param Collection<Card> $playerCards
     * @param Card $card
     * @return string[]
     */
    public function getPossibleSpacesForCard(Collection $playerCards, Card $card): array {
        $spaces = [];
        $playerCardsFlat = $playerCards->values();

        if ($card->rowEffect !== null) {
            for ($row = 1; $row <= 3; $row++) {
                if (!array_any($playerCardsFlat, fn($c) => $c->column === 0 && $c->row === $row)) {
                    $spaces[] = $row.'-0';
                }
            }
            if (count($spaces) > 0) {
                return $spaces;
            }
        } else if ($card->columnEffect !== null) {
            for ($column = 1; $column <= 3; $column++) {
                if (!array_any($playerCardsFlat, fn($c) => $c->row === 0 && $c->column === $column)) {
                    $spaces[] = '0-'.$column;
                }
            }
            if (count($spaces) > 0) {
                return $spaces;
            }
        } else {
            [$cardRow, $cardColumn] = Game::getRowColumnFromValue($card->value);
            $spaceCards = $playerCards->filter(fn($c) => $c->row === $cardRow && $c->column === $cardColumn);
            if (count($spaceCards) < 2) {
                // can play the card on the value
                $spaces[] = $cardRow.'-'.$cardColumn;
                return $spaces;
            }
        }
         
        // must place on any other empty spot
        for ($row = 1; $row <= 3; $row++) {
            for ($column = 1; $column <= 3; $column++) {
                if ($playerCards->count(fn($c) => $c->row === $row && $c->column === $column) == 0 && ($card->value === null || $card->value != Game::getValueFromRowColumn($row, $column))) {
                    $spaces[] = $row.'-'.$column;
                }
            }
        }
        return $spaces;
    }

    /**
     * @param Collection<Card> $playerCards
     */
    public function getDetailledScore(Collection $playerCards, int $roundNumber, bool $isFlowerPowerExpansion): DetailledScore {
        $detailledScore = new DetailledScore();

        $rowsWithCancelledCrosses = [];
        $facedownCardsCount = 0;
        /** @var array<int,?Card> */
        $validatedCards = [];
        /** @var array<string,?Card> */
        $visibleCards = [];
        $min = $this->game->isLittleGiantsExpansion() ? 0 : 1;
        for ($row = $min; $row <= 3; $row++) {
            for ($column = $min; $column <= 3; $column++) {
                if ($row === 0 && $column === 0) {
                    continue;
                }
                $spaceCards = $playerCards->filter(fn($c) => $c->row === $row && $c->column === $column);
                $visibleCards[$row.'-'.$column] = null;
                if ($spaceCards->count() > 0) {
                    $topCard = $spaceCards->last();

                    if ($topCard->value) {
                        if ($topCard->value === Game::getValueFromRowColumn($row, $column)) {
                            $visibleCards[$row.'-'.$column] = $topCard;
                        } else {
                            if ($isFlowerPowerExpansion) {
                                $facedownCardsCount++;
                            }
                        }
                    } else { // row/column card
                        if ($row === 0 || $column === 0) {
                            $visibleCards[$row.'-'.$column] = $topCard;
                        } else {
                            if ($isFlowerPowerExpansion) {
                                $facedownCardsCount++;
                            }
                        }
                    }
                }

                if ($row > 0 && $column > 0) {
                    $value = ($row-1) * 3 + $column;
                    if ($spaceCards->count() >= 2) {
                        $validatedCards[$value] = $spaceCards->last();
                    }
                }

                if ($column === 0 && $spaceCards->count() > 0) {
                    $topCard = $spaceCards->last();
                    if ($topCard->rowEffect === 40) {
                        $rowsWithCancelledCrosses = [$row];
                    }
                }
            }
        }
        
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

        foreach ($validatedCards as $value => $card) {
            if ($card) {
                $detailledScore->validatedCardPoints += $value;
            }
        }

        if ($min === 0) {
            for ($row = $min; $row <= 3; $row++) {
                for ($column = $min; $column <= 3; $column++) {
                    if (($row === 0 && $column === 0) || ($row > 0 && $column > 0)) {
                        continue;
                    }
                    $spaceCards = $playerCards->filter(fn(Card $c) => $c->row === $row && $c->column === $column);
                    if ($spaceCards->isEmpty()) {
                        continue;
                    }
                    $fullEffect = 0;
                    $card = $spaceCards->last();
                    $concernedCards = null;
                    if ($row === 0 && $column > 0) {
                        $fullEffect = $card->columnEffect;
                        $concernedCards = array_values(array_filter($visibleCards, fn(?Card $c) => $c !== null && $c->column === $column));
                    } else if ($column === 0 && $row > 0) {
                        $fullEffect = $card->rowEffect;
                        $concernedCards = array_values(array_filter($visibleCards, fn(?Card $c) => $c !== null && $c->row === $row));
                    }
                    $effect = intdiv($fullEffect, 10);
                    if ($effect === 4) {
                        continue; // cancels all crosses on faceup cards in its row: handled by $rowsWithCancelledCrosses
                    }
                    $effectParam = $fullEffect % 10;
                    $cardSpirals = 0;
                    switch ($effect) {
                        case 1: // 3 spirals for each validated card in its column.
                            $cardSpirals = 3 * count(array_filter($concernedCards, fn(Card $c) => $c->locationArg === 1));
                            break;
                        case 2: // 4 spirals for each faceup card in its column that has no spirals.
                            $cardSpirals = 4 * count(array_filter($concernedCards, fn(Card $c) => $c->id !== $card->id && ($c->spirals ?? 0) === 0 && ($c->spiralsPerFacedownCard ?? 0) === 0));
                            break;
                        case 3: // 2 spirals for each faceup card in its row that is the indicated color. (including itself)
                            $cardSpirals = 2 * count(array_filter($concernedCards, fn(Card $c) => !$c->flipped && in_array($effectParam, $c->colors)));
                            break;
                        case 5: // 2 or 3 spirals (as shown) for each faceup card in its row or column that is not validated.
                            $cardSpirals = $effectParam * count(array_filter($concernedCards, fn(Card $c) => $c->id !== $card->id && $c->locationArg === 0));
                            break;
                        default:
                            throw new SystemException("Unknown effect");
                    }                    
                    $spiralsPoints += $cardSpirals;
                    $detailledScore->computedSpiralsPerCard[$card->id] = $cardSpirals;
                }
            }
        }

        foreach ($visibleCards as $card) {
            if ($card) {
                $cardSpirals = null;
                $cardCrosses = null;
                if ($card->spirals != 0) {
                    if ($card->spirals == -1) {
                        $cardSpirals = $colorsCounts[$card->colors[0]]; // spiral -1 is always of the single color of the card
                    } else {
                        $cardSpirals = $card->spirals;
                    }
                }
                if ($card->crosses != 0 && !in_array($card->row, $rowsWithCancelledCrosses)) {
                    if ($card->crosses < 0) {
                        $cardCrosses += $colorsCounts[-$card->crosses]; // X per color is coded as negative cross
                    } else {
                        $cardCrosses += $card->crosses;
                    }
                }
                if ($card->spiralsPerFacedownCard > 0) {
                    $cardSpirals = $card->spiralsPerFacedownCard * $facedownCardsCount;
                }

                $spiralsPoints += $cardSpirals;
                if ($cardSpirals !== null && ($card->spirals < 0 || $card->spiralsPerFacedownCard > 0)) {// last conditions to only display non obvious ones
                    $detailledScore->computedSpiralsPerCard[$card->id] = $cardSpirals;
                }
                $crossesPoints += $cardCrosses;
                if ($cardCrosses !== null && $card->crosses < 0) { // last condition to only display non obvious ones
                    $detailledScore->computedCrossesPerCard[$card->id] = $cardCrosses;
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

        $min = $this->game->isLittleGiantsExpansion() ? 0 : 1;
        for ($row = $min; $row <= 3; $row++) {
            for ($column = $min; $column <= 3; $column++) {
                if ($row === 0 && $column === 0) {
                    continue;
                }
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
        $count = count($this->getCardsFromSpaces($playerId)[$row.'-'.$column]);
        $locationValue = Game::getValueFromRowColumn($row, $column);
        if ($card->rowEffect) {
            $locationValue = "row$row";
        } else if ($card->columnEffect) {
            $locationValue = "column$row";
        }
        $card->location = "player-$playerId-".$locationValue;
        $card->locationArg = $count;
        $this->cards->moveItem($card->id, [$card->location, $card->locationArg]);
        $card->row = $row;
        $card->column = $column;
        $space = Game::getValueFromRowColumn($row, $column);
        $playedVisible = $card->value ? $space == $card->value : $card->row === 0 || $card->column === 0;
        $card->flipped = !$playedVisible;
        $this->cards->updateItem($card, ['row', 'column', 'flipped']);
    }
    
    public function keepCard(int $playerId, Card $hiddenCard, Card $visibleCard, int $row, int $column): void {
        $location = "player-$playerId-".Game::getValueFromRowColumn($row, $column);
        $hiddenCard->location = $location;
        $hiddenCard->locationArg = 0;
        $hiddenCard->row = $row;
        $hiddenCard->column = $column;
        $hiddenCard->flipped = true;
        $visibleCard->location = $location;
        $visibleCard->locationArg = 1;
        $visibleCard->row = $row;
        $visibleCard->column = $column;
        $visibleCard->flipped = false;

        $this->cards->moveItem($hiddenCard->id, [$hiddenCard->location, $hiddenCard->locationArg]);
        $this->cards->moveItem($visibleCard->id, [$visibleCard->location, $visibleCard->locationArg]);
        $this->cards->updateItem($hiddenCard, ['row', 'column', 'flipped']);
        $this->cards->updateItem($visibleCard, ['row', 'column', 'flipped']);
    }

    public function reshuffleAllCardsToDeck() {
        $this->cards->moveAllItemsInLocation(null, ['deck']);
        $this->cards->shuffle(['deck']);
        $this->cards->updateAllItems('flipped', false);
    }

    /**
     * @param Collection<Card> $playerCards
     */    
    public function getPointsFromZombieKeepCardChoice(
        Game $game,
        Collection $playerCards,
        int $roundNumber,
        bool $isFlowerPowerExpansion,
        Card $card,
        int $choice, // 0|1
    ): int {
        if ($card->value === null) {
            throw new SystemException('Cannot choose to keep a little giant card over another card');
        }
        [$row, $column] = Game::getRowColumnFromValue($card->value);
        $existingCard = $playerCards->find(fn($c) => $c->row === $row && $c->column === $column);
        if ($existingCard->value === null) {
            throw new SystemException('Cannot choose to keep a card over a little giant card');
        }
        $newCollection = $playerCards->filter(fn($c) => $c->id !== $existingCard->id);
        if ($choice === 0) {
            $newCollection = $newCollection->add(Card::onlyId($card))->add($existingCard);
        } else {
            $newCollection = $newCollection->add(Card::onlyId($existingCard))->add($card);
        }

        return $game->cardManager->getDetailledScore($playerCards, $roundNumber, $isFlowerPowerExpansion)->points;
    }
}