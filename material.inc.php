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

require_once('modules/php/objects/card.php');

$this->COLORS = [
  clienttranslate('Multicolor'),
  clienttranslate('Blue'),
  clienttranslate('Green'),
  clienttranslate('Yellow'),
  clienttranslate('Red'),
];

$this->CARDS = [
  0 => [
    1 => new CardType(2),
    2 => new CardType(3),
    3 => new CardType(4),
    4 => new CardType(6, 0, 1),
    5 => new CardType(7, 0, 1),
    6 => new CardType(8, 0, 1),
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
    9 => new CardType(5, 0, 2),
    10 => new CardType(5, -1),
    11 => new CardType(6, 1),
    12 => new CardType(6, 0, 1),
    13 => new CardType(7, 0, 3),
    14 => new CardType(8, 0, 3),
    15 => new CardType(9, 0, 6),
    16 => new CardType(9, 0, 1),
  ],

  2 => [
    1 => new CardType(1, 5),
    2 => new CardType(2, 3),
    3 => new CardType(3, 2),
    4 => new CardType(3, -1),
    5 => new CardType(4, 4),
    6 => new CardType(4, 0, 1),
    7 => new CardType(5),
    8 => new CardType(5, 0, 1),
    9 => new CardType(5, -1),
    10 => new CardType(6, 0, 4),
    11 => new CardType(6, 0, 1),
    12 => new CardType(6, 1),
    13 => new CardType(7, 0, 2),
    14 => new CardType(7),
    15 => new CardType(8, 0, 2),
    16 => new CardType(9, 0, 4),
  ],

  3 => [
    1 => new CardType(1, 4),
    2 => new CardType(2, 2),
    3 => new CardType(2, -1),
    4 => new CardType(3, 5),
    5 => new CardType(3),
    6 => new CardType(4, 3),
    7 => new CardType(4, 0, 1),
    8 => new CardType(5, 0, 2),
    9 => new CardType(5),
    10 => new CardType(5, -1),
    11 => new CardType(6, 0, 3),
    12 => new CardType(6),
    13 => new CardType(7, 0, 5),
    14 => new CardType(7, 1),
    15 => new CardType(8, 0, 1),
    16 => new CardType(9, 0, 2),
  ],

  4 => [
    1 => new CardType(1, 3),
    2 => new CardType(1, -1),
    3 => new CardType(2, 5),
    4 => new CardType(3, 4),
    5 => new CardType(4, 2),
    6 => new CardType(4),
    7 => new CardType(5, 0, 1),
    8 => new CardType(5, -1),
    9 => new CardType(5),
    10 => new CardType(6, 0, 2),
    11 => new CardType(6),
    12 => new CardType(7, 0, 4),
    13 => new CardType(7, 0, 1),
    14 => new CardType(8, 0, 5),
    15 => new CardType(8),
    16 => new CardType(9),
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
  clienttranslate('Multicolor'),
  clienttranslate('Blue'),
  clienttranslate('Green'),
  clienttranslate('Yellow'),
  clienttranslate('Red'),
];


