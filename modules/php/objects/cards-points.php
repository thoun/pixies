<?php

function array_find(array $array, callable $fn) {
    foreach ($array as $value) {
        if($fn($value)) {
            return $value;
        }
    }
    return null;
}

function array_find_key(array $array, callable $fn) {
    foreach ($array as $key => $value) {
        if($fn($value)) {
            return $key;
        }
    }
    return null;
}

class CardsPoints {
    public array $detailledPoints;
    public int $totalPoints;
    public int $colorBonus;

    public function __construct(array $tableCards, array $handCards) {
        $cards = array_merge($tableCards, $handCards);

        $numberByColor = [];
        foreach($cards as $card) {
            if (array_key_exists($card->color, $numberByColor)) {
                $numberByColor[$card->color]++;
            } else {
                $numberByColor[$card->color] = 1;
            }
        }
        $this->colorBonus = count($numberByColor) > 0 ? max($numberByColor) : 0;

        if (count(array_filter($handCards, fn($card) => $card->category == SPECIAL && $card->family == SEAHORSE)) > 0) {
            $this->totalPoints = -1;
            foreach ([SHELL, OCTOPUS, PENGUIN, SAILOR] as $collectionBonus) {
                $detailledPoints = $this->getPoints($tableCards, $handCards, $numberByColor, $collectionBonus); // only works for 1, to change if multiple seahorses one day
                $totalPoints = $this->getTotalPoints($detailledPoints);

                if ($totalPoints > $this->totalPoints) {
                    $this->detailledPoints = $detailledPoints;            
                    $this->totalPoints = $totalPoints;
                }
            }
        } else {
            $this->detailledPoints = $this->getPoints($tableCards, $handCards, $numberByColor);            
            $this->totalPoints = $this->getTotalPoints($this->detailledPoints);
        }
    }

    public function getTotalPoints(array $detailledPoints) {
        return array_reduce($detailledPoints, fn($a, $b) => $a + $b, 0);
    }

    public function getPoints(array $tableCards, array $handCards, array $numberByColor, /*int|null*/$collectionBonus = null) {
        $cards = array_merge($tableCards, $handCards);

        $mermaidPoints = 0;
        $pairPoints = 0;
        $collectorPoints = 0;
        $multiplierPoints = 0;

        $mermaidCards = array_values(array_filter($cards, fn($card) => $card->category == MERMAID));
        $pairHandCards = array_values(array_filter($handCards, fn($card) => $card->category == PAIR));
        $pairTableCards = array_values(array_filter($tableCards, fn($card) => $card->category == PAIR));
        $collectionCards = array_values(array_filter($cards, fn($card) => $card->category == COLLECTION));
        $multiplierCards = array_values(array_filter($cards, fn($card) => $card->category == MULTIPLIER));
        $specialCardsInTable = array_values(array_filter($tableCards, fn($card) => $card->category == SPECIAL));

        // Mermaids
        $mermaidCount = count($mermaidCards);
        while ($mermaidCount > 0) {
            if (count($numberByColor) == 0) {
                break;
            }

            $maxColor = count($numberByColor) > 0 ? max($numberByColor) : 0;
            $mermaidPoints += $maxColor;

            $maxColorIndex = array_find_key($numberByColor, fn($val) => $val == $maxColor);
            unset($numberByColor[$maxColorIndex]);

            $mermaidCount--;
        }

        $pairPoints += floor(count($pairTableCards) / 2);

        $remainingPairCards = $pairHandCards; // copy
        usort($remainingPairCards, fn($a, $b) => $b->family - $a->family); // so a pair of crabs & a pair of lobster would be match lobster+crab * 2 instead of 2 crabs & 2 lone lobsters
        while (count($remainingPairCards) > 0) {
            $card = $remainingPairCards[0];
            $matchingCard = null;
            foreach ($card->matchFamilies as $matchFamily) {
                $matchingCard = array_find($remainingPairCards, fn($c) => $c->family == $matchFamily && $c->id != $card->id);
                if ($matchingCard != null) {
                    break;
                }
            }
            if ($matchingCard !== null) {
                $pairPoints += 1;
            }
            $remainingPairCards = array_values(array_filter($remainingPairCards, fn($c) => $c->id != $card->id && ($matchingCard == null || $matchingCard->id != $c->id)));
        }

        // Collections
        for ($family = SHELL; $family <= SAILOR; $family++) {
            $count = count(array_values(array_filter($collectionCards, fn($card) => $card->family == $family)));
            if ($count > 0) {
                if ($family === $collectionBonus && $count < count(COLLECTION_POINTS[$family])) {
                    $count++;
                }
                $collectorPoints += COLLECTION_POINTS[$family][$count - 1];
            }
        }
        
        // Multipliers
        for ($family = LIGHTHOUSE; $family <= CAST_CRAB; $family++) {
            $multiplierCardsOfFamily = array_values(array_filter($multiplierCards, fn($card) => $card->family == $family));
            if (count($multiplierCardsOfFamily) > 0) {
                $multiplierCard = $multiplierCardsOfFamily[0];
                $collectionCardCount = count(array_values(array_filter($cards, fn($card) => $card->category == $multiplierCard->matchCategory && $card->family == $multiplierCard->matchFamily)));
                $multiplierPoints += $multiplierCard->points * $collectionCardCount;
            }
        }
        
        // Special
        $pairPoints += count(array_filter($specialCardsInTable, fn($card) => $card->family == STARFISH)) * 2;

        return [$mermaidPoints, $pairPoints, $collectorPoints, $multiplierPoints];
    }
}
?>