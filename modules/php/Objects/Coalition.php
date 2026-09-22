<?php
declare(strict_types=1);

namespace Bga\Games\Pixies\Objects;

class Coalition {  
    /**
     * @param (int[])[] $alreadyCounted
     */
    public function __construct(
        public int $row,
        public int $column,
        public int $color,
        public int $size = 0,
        public array $alreadyCounted = [],
    ) {
    } 
}
