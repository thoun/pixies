<?php

class CardType {
  
    public function __construct(
        public ?int $value, // for hidden cards
        public int $spirals = 0, // -1 = 1 per color
        public int $spiralsPerFacedownCard = 0,
        public int $crosses = 0, // -1 = 1 per color
    ) {
    } 
}

class Card extends CardType {
    public int $id;
    public string $location;
    public int $locationArg;
    public ?int $type = null; // for hidden cards
    public array $colors;
    public int $index;

    public function __construct($dbCard, $CARDS_TYPE) {
        $this->id = intval($dbCard['id']);
        $this->location = $dbCard['location'];
        $this->locationArg = intval($dbCard['location_arg']);
        if ($dbCard['type'] !== null) {
            $this->type = intval($dbCard['type']);
            $this->index = intval($dbCard['type_arg']);

            $cardType = $CARDS_TYPE[$this->type][$this->index];
            $this->value = $cardType->value;
            $this->spirals = $cardType->spirals;
            $this->crosses = $cardType->crosses;
            $this->spiralsPerFacedownCard = $cardType->spiralsPerFacedownCard;
            
            $this->colors = [$this->type];
            if ($this->type === 0) {
                $this->colors = [1, 2, 3, 4];
            } else if ($this->type >= 10) {
                $this->colors = [floor($this->type / 10), $this->type % 10];
            }
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