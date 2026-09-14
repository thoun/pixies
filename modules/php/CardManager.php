<?php

declare(strict_types=1);

namespace Bga\Games\Pixies;

use Bga\GameFramework\Components\ItemManager\ItemLocation;
use Bga\GameFramework\Components\ItemManager\ItemManager;
use Bga\GameFramework\Helpers\Collection;
use Bga\Games\Pixies\Objects\Card;
use Bga\Games\Pixies\Objects\Coalition;
use Bga\Games\Pixies\Objects\DetailledScore;

class CardManager
{
    /** @var ItemManager<Card> */
    public ItemManager $cards;

    public function __construct(private Game $game)
    {
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

    /*public function setup(): void
    {
        $cards = [];
        foreach ($this->game->card_types['suites'] as $suit => $suitInfo) {
            foreach ($this->game->card_types['types'] as $value => $valueInfo) {
                $cards[] = [
                    'location' => 'deck',
                    'suit' => $suit,
                    'value' => $value,
                ];
            }
        }
        $this->cards->createItems($cards);
    }*/

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

    function getPlayerCardCount(int $playerId): int {
        $playerCards = $this->getCardsFromSpaces($playerId);
        $playerCardCount = array_reduce(array_map(fn($cards) => count($cards) > 0 ? 1 : 0, $playerCards), fn($a, $b) => $a + $b, 0);

        return $playerCardCount;
    }
}