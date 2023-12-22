<?php

class CardType {
    public ?int $value;
    public int $spirals; // -1 = 1 per color
    public int $crosses;
  
    public function __construct(int $value, int $spirals = 0, int $crosses = 0) {
        $this->value = $value;
        $this->spirals = $spirals;
        $this->crosses = $crosses;
    } 
}

class Card extends CardType {
    public int $id;
    public string $location;
    public int $locationArg;
    public int $color;
    public int $index;

    public function __construct($dbCard, $CARDS_TYPE) {
        $this->id = intval($dbCard['id']);
        $this->location = $dbCard['location'];
        $this->locationArg = intval($dbCard['location_arg']);
        if ($dbCard['type'] !== null) {
            $this->color = intval($dbCard['type']);
            $this->index = intval($dbCard['type_arg']);

            $cardType = $CARDS_TYPE[$this->color][$this->index];
            $this->value = $cardType->value;
            $this->spirals = $cardType->spirals;
            $this->crosses = $cardType->crosses;
        } else {
            $this->value = null;
        }
    } 

    public static function onlyId(?Card $card) {
        if ($card == null) {
            return null;
        }
        
        return new Card([
            'id' => $card->id,
            'location' => $card->location,
            'location_arg' => $card->locationArg,
            'type' => null
        ], null);
    }

    public static function onlyIds(array $cards) {
        return array_map(fn($card) => self::onlyId($card), $cards);
    }
}
?>