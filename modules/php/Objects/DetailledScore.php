<?php
declare(strict_types=1);

namespace Bga\Games\Pixies\Objects;

class DetailledScore {
    public int $validatedCardPoints = 0;
    public int $largestColorZonePoints = 0;
    public int $spiralsPoints = 0;
    public int $crossesPoints = 0;
    public int $spiralsAndCrossesPoints = 0;
    public int $facedownCardsPoints = 0;
    public int $points = 0;

    /** @var array<int,int> */
    public array $computedSpiralsPerCard = [];
    /** @var array<int,int> */
    public array $computedCrossesPerCard = [];
    public int $largestColorZoneColor = 0;
    /** @var (int[])[] */
    public array $largestColorZoneCardCoordinates = [];
  
    public function __construct() {
    } 
}