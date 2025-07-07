<?php

require_once(__DIR__.'/objects/card.php');

trait UtilTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////

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

    function array_some(array $array, callable $fn) {
        foreach ($array as $value) {
            if($fn($value)) {
                return true;
            }
        }
        return false;
    }
    
    function array_every(array $array, callable $fn) {
        foreach ($array as $value) {
            if(!$fn($value)) {
                return false;
            }
        }
        return true;
    }

    function array_identical(array $a1, array $a2) {
        if (count($a1) != count($a2)) {
            return false;
        }
        for ($i=0;$i<count($a1);$i++) {
            if ($a1[$i] != $a2[$i]) {
                return false;
            }
        }
        return true;
    }

    function setGlobalVariable(string $name, /*object|array*/ $obj) {
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

    function getCardFromDb(/*array|null*/ $dbCard) {
        if ($dbCard == null) {
            return null;
        }
        return new \Card($dbCard, $this->CARDS + $this->FLOWER_POWER_CARDS);
    }

    function getCardsFromDb(array $dbCards) {
        return array_map(fn($dbCard) => $this->getCardFromDb($dbCard), array_values($dbCards));
    }

    function getCardsFromSpace(int $playerId, int $value) {
        return $this->getCardsFromDb($this->cards->getCardsInLocation("player-$playerId-$value", null, 'location_arg'));
    }

    function getCardsFromSpaces(int $playerId) {
        $spaces = [];

        for ($i = 1; $i <= 9; $i++) {
            $spaces[$i] = $this->getCardsFromSpace($playerId, $i);
            if (count($spaces[$i]) == 2 || (count($spaces[$i]) == 1 && $spaces[$i][0]->value != $i)) {
                $spaces[$i][0] = \Card::onlyId($spaces[$i][0]);
            }
        }

        return $spaces;
    }

    function getValidatedCards(int $playerId) {
        $spaces = $this->getCardsFromSpaces($playerId);

        $cards = array_map(fn($space) => count($space) == 2 ? $space[1] : null, $spaces);

        return $cards;
    }

    function getVisibleCards(int $playerId) {
        $spaces = $this->getCardsFromSpaces($playerId);

        $cards = array_map(fn($space) => count($space) == 2 ? $space[1] : (count($space) == 1 && $space[0]->value !== null ? $space[0] : null), $spaces);

        return $cards;
    }

    function countFacedownCards(int $playerId): int {
        $spaces = $this->getCardsFromSpaces($playerId);
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
        foreach ($CARDS as $type => $cardsTypes) {
            foreach ($cardsTypes as $index => $cardType) {
                $cardsToGenerate[] = [ 'type' => $type, 'type_arg' => $index, 'nbr' => 1 ];
            }
        }
        $this->cards->createCards($cardsToGenerate, 'deck');
        $this->cards->shuffle('deck');
    }

    function getPlayerScore(int $playerId) {
        return intval($this->getUniqueValueFromDB("SELECT player_score FROM player where `player_id` = $playerId"));
    }

    function incPlayerScore(int $playerId, int $roundScore, $message = '', $args = []) {
        $this->DbQuery("UPDATE player SET `player_score` = `player_score` + $roundScore WHERE player_id = $playerId");
            
        $this->notify->all('score', $message, [
            'playerId' => $playerId,
            'newScore' => $this->getPlayerScore($playerId),
            'incScore' => $roundScore,
            'round' => intval($this->getStat('roundNumber')),
        ] + $args);
    }

    function getRemainingCardsInDeck() {
        return intval($this->cards->countCardInLocation('deck'));
    }

    function getColorZoneSize(array $validatedCards, $coalition, int $currentSpace) {
        // we check we don't count twice the same space
        if (array_search($currentSpace, $coalition->alreadyCounted) !== false) {
            return;
        }

        $coalition->size++;
        $coalition->alreadyCounted = array_merge($coalition->alreadyCounted, [$currentSpace]);

        // we only take cards having same color
        $filteredNeigbours = array_filter($this->NEIGHBOURS[$currentSpace], fn($neighbour) =>
            $validatedCards[$neighbour] && in_array($coalition->color, $validatedCards[$neighbour]->colors)
        );

        foreach ($filteredNeigbours as $filteredNeigbour) {
            $this->getColorZoneSize($validatedCards, $coalition, $filteredNeigbour);
        }
    }

    function getLargestColorZone(array $visibleCards) {
        $topCoalition = null;

        for ($space = 1; $space <= 9; $space++) {
            $cardInSpace = $visibleCards[$space];

            if ($cardInSpace) {
                foreach ($cardInSpace->colors as $color) {
                    $coalition = new stdClass();
                    $coalition->space = $space;
                    $coalition->size = 0;
                    $coalition->color = $color;
                    $coalition->alreadyCounted = [];
                    $this->getColorZoneSize($visibleCards, $coalition, $space);
                    
                    if (!$topCoalition || $coalition->size > $topCoalition->size) {
                        $topCoalition = $coalition;
                    }
                }
            }
        }

        return $topCoalition->size;
    }
    
    function scoreRound() {
        $roundNumber = intval($this->getStat('roundNumber'));
        $playersIds = $this->getPlayersIds();
        $result = [];
        $isFlowerPowerExpansion = $this->isFlowerPowerExpansion();

        foreach ($playersIds as $playerId) {
            $detailledScore = new stdClass();
            $validatedCards = $this->getValidatedCards($playerId);
            $visibleCards = $this->getVisibleCards($playerId);
            $facedownCardsCount = $isFlowerPowerExpansion ? $this->countFacedownCards($playerId) : 0;
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
            $playerId == 2343493 && debug($colorsCounts, $visibleCards);

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
            $result[$playerId] = $detailledScore;  
        
            $this->incStat($detailledScore->validatedCardPoints, 'pointsValidatedCard');
            $this->incStat($detailledScore->validatedCardPoints, 'pointsValidatedCard', $playerId);
            $this->incStat($spiralsPoints, 'pointsSpirals');
            $this->incStat($spiralsPoints, 'pointsSpirals', $playerId);
            $this->incStat($crossesPoints, 'pointsLostCrosses');
            $this->incStat($crossesPoints, 'pointsLostCrosses', $playerId);
            $this->incStat($detailledScore->largestColorZonePoints, 'pointsColorZone');
            $this->incStat($detailledScore->largestColorZonePoints, 'pointsColorZone', $playerId);
            $this->incStat($facedownCardsPoints, 'pointsFacedownCards');
            $this->incStat($facedownCardsPoints, 'pointsFacedownCards', $playerId);
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
                    'color1' => $this->COLORS[floor($card->type / 10)],
                    'color2' => $this->COLORS[$card->type % 10],
                ]
            ] : $this->COLORS[$card->type];
        }

        return $args;
    }
}
