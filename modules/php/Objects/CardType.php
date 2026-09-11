<?php
declare(strict_types=1);

namespace Bga\Games\Pixies\Objects;

class CardType {
  
    public function __construct(
        public ?int $value = null, // for hidden cards or little giants
        public int $spirals = 0, // -1 = 1 per color
        public int $spiralsPerFacedownCard = 0,
        public int $crosses = 0, // -1 = 1 per color
        public ?int $rowEffect = null,
        public ?int $columnEffect = null,
    ) {
    } 
}
