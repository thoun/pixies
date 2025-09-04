<?php
declare(strict_types=1);

namespace Bga\Games\Pixies\objects;

class DetailledScore {
    public int $validatedCardPoints = 0;
    public int $largestColorZonePoints = 0;
    public int $spiralsPoints = 0;
    public int $crossesPoints = 0;
    public int $spiralsAndCrossesPoints = 0;
    public int $facedownCardsPoints = 0;
    public int $points = 0;
  
    public function __construct() {
    } 
}