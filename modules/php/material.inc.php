<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Pixies implementation : © <Your name here> <Your email address here>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * material.inc.php
 *
 * Pixies game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

$this->CARDS = [
  0 => [
    1 => new CardType(2),
    2 => new CardType(3),
    3 => new CardType(4),
    4 => new CardType(6, crosses: 1),
    5 => new CardType(7, crosses: 1),
    6 => new CardType(8, crosses: 1),
  ],

  1 => [
    1 => new CardType(1, 6),
    2 => new CardType(2, 4),
    3 => new CardType(3, 3),
    4 => new CardType(3, 1),
    5 => new CardType(4, 1),
    6 => new CardType(4, -1),
    7 => new CardType(4),
    8 => new CardType(5),
    9 => new CardType(5, crosses: 2),
    10 => new CardType(5, -1),
    11 => new CardType(6, 1),
    12 => new CardType(6, crosses: 1),
    13 => new CardType(7, crosses: 3),
    14 => new CardType(8, crosses: 3),
    15 => new CardType(9, crosses: 6),
    16 => new CardType(9, crosses: 1),
  ],

  2 => [
    1 => new CardType(1, 5),
    2 => new CardType(2, 3),
    3 => new CardType(3, 2),
    4 => new CardType(3, -1),
    5 => new CardType(4, 4),
    6 => new CardType(4, crosses: 1),
    7 => new CardType(5),
    8 => new CardType(5, crosses: 1),
    9 => new CardType(5, -1),
    10 => new CardType(6, crosses: 4),
    11 => new CardType(6, crosses: 1),
    12 => new CardType(6, 1),
    13 => new CardType(7, crosses: 2),
    14 => new CardType(7),
    15 => new CardType(8, crosses: 2),
    16 => new CardType(9, crosses: 4),
  ],

  3 => [
    1 => new CardType(1, 4),
    2 => new CardType(2, 2),
    3 => new CardType(2, -1),
    4 => new CardType(3, 5),
    5 => new CardType(3),
    6 => new CardType(4, 3),
    7 => new CardType(4, crosses: 1),
    8 => new CardType(5, crosses: 2),
    9 => new CardType(5),
    10 => new CardType(5, -1),
    11 => new CardType(6, crosses: 3),
    12 => new CardType(6),
    13 => new CardType(7, crosses: 5),
    14 => new CardType(7, 1),
    15 => new CardType(8, crosses: 1),
    16 => new CardType(9, crosses: 2),
  ],

  4 => [
    1 => new CardType(1, 3),
    2 => new CardType(1, -1),
    3 => new CardType(2, 5),
    4 => new CardType(3, 4),
    5 => new CardType(4, 2),
    6 => new CardType(4),
    7 => new CardType(5, crosses: 1),
    8 => new CardType(5, -1),
    9 => new CardType(5),
    10 => new CardType(6, crosses: 2),
    11 => new CardType(6),
    12 => new CardType(7, crosses: 4),
    13 => new CardType(7, crosses: 1),
    14 => new CardType(8, crosses: 5),
    15 => new CardType(8),
    16 => new CardType(9),
  ],
];

$this->FLOWER_POWER_CARDS = [
  12 => [ // Blue and Green
    1 => new CardType(8, spiralsPerFacedownCard: 1, crosses: 1),
    2 => new CardType(2, spirals: 6, crosses: -3),
  ],
  13 => [ // Blue and Yellow
    1 => new CardType(5, spiralsPerFacedownCard: 2),
    2 => new CardType(1, spirals: 7, crosses: -4),
    3 => new CardType(8, spirals: 1, crosses: -4),
  ],
  14 => [ // Blue and Red
    1 => new CardType(2, spiralsPerFacedownCard: 3, crosses: 1),
    2 => new CardType(7, spirals: 2, crosses: -2),
  ],
  23 => [ // Green and Yellow
    1 => new CardType(1, spiralsPerFacedownCard: 3),
    2 => new CardType(9, crosses: -1),
  ],
  24 => [ // Green and Red
    1 => new CardType(5, spiralsPerFacedownCard: 2, crosses: 1),
    2 => new CardType(3, spirals: 5, crosses: -1),
    3 => new CardType(6, spirals: 3, crosses: -3),
  ],
  34 => [ // Yellow and Red
    1 => new CardType(9, spiralsPerFacedownCard: 1, crosses: 2),
    2 => new CardType(4, spirals: 4, crosses: -2),
  ],

];

/*
 * Color zone
 */
$this->NEIGHBOURS = [
  1 => [2, 4],
  2 => [1, 3, 5],
  3 => [2, 6],
  4 => [1, 5, 7],
  5 => [2, 4, 6, 8],
  6 => [3, 5, 9],
  7 => [4, 8],
  8 => [5, 7, 9],
  9 => [6, 8],
];

/*
 * Colors
 */
$this->COLORS = [
  0 => clienttranslate('Multicolor'),
  1 => clienttranslate('Blue'),
  2 => clienttranslate('Green'),
  3 => clienttranslate('Yellow'),
  4 => clienttranslate('Red'),
];


