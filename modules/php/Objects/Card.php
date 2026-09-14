<?php
declare(strict_types=1);

namespace Bga\Games\Pixies\Objects;

use Bga\GameFramework\Components\ItemManager\Item;
use Bga\GameFramework\Components\ItemManager\ItemField;
use Bga\GameFramework\Components\ItemManager\ItemFieldKind;
use Bga\Games\Pixies\Game;

#[Item('card')]
class Card extends CardType
{
    #[ItemField(kind: ItemFieldKind::ID, dbField: 'card_id')]
    public int $id;

    #[ItemField(kind: ItemFieldKind::LOCATION, locationIndex: 0, dbField: 'card_location')]
    public string $location;

    #[ItemField(kind: ItemFieldKind::LOCATION, locationIndex: 1, dbField: 'card_location_arg')]
    public ?int $locationArg;

    #[ItemField(kind: ItemFieldKind::ORDER)]
    public int $order = 0;

    #[ItemField(dbField: 'card_type')]
    public ?int $type;

    #[ItemField(dbField: 'card_type_arg')]
    public int $index;

    /** @var int[] */
    public array $colors;

    public function setup(array $dbCard) {
        $CARDS_TYPE = Game::$CARDS + Game::$FLOWER_POWER_CARDS;
        for ($i = 0; $i <= 4; $i++) {
            $CARDS_TYPE[$i] += Game::$LITTLE_GIANTS_CARDS[$i];
        }  


        $this->id = intval($dbCard['card_id']);
        $this->location = $dbCard['card_location'];
        $this->locationArg = intval($dbCard['card_location_arg']);
        if ($dbCard['card_type'] !== null) {
            $this->type = intval($dbCard['card_type']);
            $this->index = intval($dbCard['card_type_arg']);

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
}

?>