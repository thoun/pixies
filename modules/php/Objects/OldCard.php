<?php
declare(strict_types=1);

namespace Bga\Games\Pixies\Objects;

use Bga\Games\Pixies\Game;

class OldCard extends CardType {
    public int $id;
    public string $location;
    public int $locationArg;
    public ?int $type = null; // for hidden cards
    /** @var int[] */
    public array $colors;
    public int $index;

    public function __construct($dbCard) {
        $CARDS_TYPE = Game::$CARDS + Game::$FLOWER_POWER_CARDS;
        for ($i = 0; $i <= 4; $i++) {
            $CARDS_TYPE[$i] += Game::$LITTLE_GIANTS_CARDS[$i];
        }  


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
                $this->colors = [intdiv($this->type, 10), $this->type % 10];
            }
        } else {
            $this->value = null;
        }
    } 

    public static function onlyId(?OldCard $card) {
        if ($card == null) {
            return null;
        }
        
        return new OldCard([
            'id' => $card->id,
            'location' => $card->location,
            'location_arg' => $card->locationArg,
            'type' => null
        ], null);
    }

    public static function onlyIds(array $cards) {
        return array_map(fn($card) => self::onlyId($card), $cards);
    }
    
    public function getRow(): ?int {
        $locationSplit = explode('-', $this->location);
        if (count($locationSplit) === 3) {
            if (str_starts_with($locationSplit[2], 'row')) {
                return intval(str_replace($locationSplit[2], 'row', ''));
            }
            if (str_starts_with($locationSplit[2], 'column')) {
                return 0;
            }
        }

        return match(intval($locationSplit[2])) {
            1 => 1,
            2 => 1,
            3 => 1,
            4 => 2,
            5 => 2,
            6 => 2,
            7 => 3,
            8 => 3,
            9 => 3,
            default => null,
        };
    }

    public function getColumn(): ?int {
        $locationSplit = explode('-', $this->location);
        if (count($locationSplit) === 3) {
            if (str_starts_with($locationSplit[2], 'row')) {
                return 0;
            }
            if (str_starts_with($locationSplit[2], 'column')) {
                return intval(str_replace($locationSplit[2], 'column', ''));
            }
        }

        return match(intval($locationSplit[2])) {
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 1,
            5 => 2,
            6 => 3,
            7 => 1,
            8 => 2,
            9 => 3,
            default => null,
        };
    }
}
?>