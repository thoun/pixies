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
        return $this->getCardsFromDb($this->cards->getCardsInLocation("player-$playerId-$value"));
    }

    function getCardsFromSpaces(int $playerId) {
        $spaces = [];
        
        for ($i = 1; $i <= 9; $i++) {
            $spaces[$i] = $this->getCardsFromSpace($playerId, $i);
        }

        return $spaces;
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
    
    function getTotalRoundNumber() {
        return 6 - count($this->getPlayersIds());
    }

    function getPlayerScore(int $playerId) {
        return intval($this->getUniqueValueFromDB("SELECT player_score FROM player where `player_id` = $playerId"));
    }

    function incPlayerScore(int $playerId, int $roundScore, $message = '', $args = []) {
        $this->DbQuery("UPDATE player SET `player_score` = `player_score` + $roundScore,  `player_score_aux` = $roundScore WHERE player_id = $playerId");
            
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

    function getPossibleOpponentsToSteal(int $stealerId) {
        $playersIds = $this->getPlayersIds();

        return array_values(array_filter($playersIds, fn($playerId) => 
            $playerId != $stealerId && intval($this->cards->countCardInLocation('hand'.$playerId)) > 0
        ));
    }

    function playableDuoCards(int $playerId) {
        $familyPairs = [];
        $pairCards = array_values(array_filter([], fn($card) => $card->category == PAIR));
        for ($family = CRAB; $family <= CRAB; $family++) {
            $familyCards = array_values(array_filter($pairCards, fn($card) => $card->family == $family));
            if (count($familyCards) > 0) {
                $matchFamilies = $familyCards[0]->matchFamilies;

                if ($this->array_some($matchFamilies, fn($matchFamily) => 
                    count(array_filter($pairCards, fn($card) => $card->family == $matchFamily)) >= ($matchFamily == $family ? 2 : 1)
                )) {
                    $familyPairs[] = $family;
                }
            }
        }

        return $familyPairs;
    }

    function getCardName(Card $card) {
        return ''; // TODO remove
    }

    function getRemainingCardsInDeck() {
        return intval($this->cards->countCardInLocation('deck'));
    }

    function getRemainingCardsInDiscard(int $number) {
        return intval($this->cards->countCardInLocation('discard'.$number));
    }

    function cardCollected(int $playerId, Card $card) {
        $number = $card->category;
        if ($number <= 4) {
            $this->incStat(1, 'cardsCollected'.$number);
            $this->incStat(1, 'cardsCollected'.$number, $playerId);
        }
    }

    function getDeckTopCard() {
        return Card::onlyId($this->getCardFromDb($this->cards->getCardOnTop('deck')));
    }
}
