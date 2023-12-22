<?php

require_once(__DIR__.'/objects/cards-points.php');

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

    function getPlayerName(int $playerId) {
        return self::getUniqueValueFromDB("SELECT player_name FROM player WHERE player_id = $playerId");
    }

    function getCardFromDb(/*array|null*/ $dbCard) {
        if ($dbCard == null) {
            return null;
        }
        return new Card($dbCard, $this->CARDS);
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
                $spaces[$i][0] = Card::onlyId($spaces[$i][0]);
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

    function getSelectedCard() {
        return $this->getCardFromDb($this->cards->getCard($this->getGlobalVariable(SELECTED_CARD_ID)));
    }

    function setupCards() {
        $cards = [];
        foreach ($this->CARDS as $color => $cardsTypes) {
            foreach ($cardsTypes as $index => $cardType) {
                $cards[] = [ 'type' => $color, 'type_arg' => $index, 'nbr' => 1 ];
            }
        }
        $this->cards->createCards($cards, 'deck');
        $this->cards->shuffle('deck');
    }

    function getPlayerScore(int $playerId) {
        return intval($this->getUniqueValueFromDB("SELECT player_score FROM player where `player_id` = $playerId"));
    }

    function incPlayerScore(int $playerId, int $roundScore, $message = '', $args = []) {
        $this->DbQuery("UPDATE player SET `player_score` = `player_score` + $roundScore WHERE player_id = $playerId");
            
        $this->notifyAllPlayers('score', $message, [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'newScore' => $this->getPlayerScore($playerId),
            'incScore' => $roundScore,
        ] + $args);
    }

    function setPlayerScore(int $playerId, int $amount, $message = '', $args = []) {
        $this->DbQuery("UPDATE player SET `player_score` = $amount WHERE player_id = $playerId");
            
        $this->notifyAllPlayers('score', $message, [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'newScore' => $amount,
            'preserve' => ['playerId'],
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
            $validatedCards[$neighbour] && in_array($validatedCards[$neighbour]->color, [$coalition->color, 0])
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
                $coalition = new stdClass();
                $coalition->space = $space;
                $coalition->size = 0;
                $coalition->color = $cardInSpace->color;
                $coalition->alreadyCounted = [];
                $this->getColorZoneSize($visibleCards, $coalition, $space);
                
                if (!$topCoalition || $coalition->size > $topCoalition->size) {
                    $topCoalition = $coalition;
                }
            }
        }

        return $topCoalition->size;
    }
    
    function scoreRound() {
        $roundNumber = intval($this->getStat('roundNumber'));
        $playersIds = $this->getPlayersIds();
        $result = [];

        foreach ($playersIds as $playerId) {
            $detailledScore = new stdClass();
            $validatedCards = $this->getValidatedCards($playerId);
            $visibleCards = $this->getVisibleCards($playerId);

            $detailledScore->validatedCardPoints = 0;
            $spiralsPoints = 0;
            $crossesPoints = 0;
            $largestColorZone = 0;
            $detailledScore->largestColorZonePoints = 0;

            foreach ($validatedCards as $space => $card) {
                if ($card) {
                    $detailledScore->validatedCardPoints += $space;
                }
            }

            foreach ($visibleCards as $space => $card) {
                if ($card) {
                    if ($card->spirals != 0) {
                        if ($card->spirals == -1) {
                            $spiralsPoints += count(array_filter($visibleCards, fn($c) => $c && in_array($c->color, [0, $card->color])));
                        } else {
                            $spiralsPoints += $card->spirals;
                        }
                    }
                    if ($card->crosses != 0) {
                        $crossesPoints += $card->crosses;
                    }
                    $colorZone = $this->getLargestColorZone($visibleCards);
                    if ($colorZone > $largestColorZone) {
                        $largestColorZone = $colorZone;
                    }
                }
            }

            $detailledScore->spiralsAndCrossesPoints = $spiralsPoints - $crossesPoints;
            $detailledScore->largestColorZonePoints = $largestColorZone * ($roundNumber + 1);

            $detailledScore->points = $detailledScore->validatedCardPoints + $detailledScore->spiralsAndCrossesPoints + $detailledScore->largestColorZonePoints;
            $result[$playerId] = $detailledScore;  
        
            $this->incStat($detailledScore->validatedCardPoints, 'pointsValidatedCard');
            $this->incStat($detailledScore->validatedCardPoints, 'pointsValidatedCard', $playerId);
            $this->incStat($spiralsPoints, 'pointsSpirals');
            $this->incStat($spiralsPoints, 'pointsSpirals', $playerId);
            $this->incStat($crossesPoints, 'pointsLostCrosses');
            $this->incStat($crossesPoints, 'pointsLostCrosses', $playerId);
            $this->incStat($detailledScore->largestColorZonePoints, 'pointsColorZone');
            $this->incStat($detailledScore->largestColorZonePoints, 'pointsColorZone', $playerId);
        }

        $this->setGlobalVariable(ROUND_RESULT, $result);

        return $result;
    }
}
