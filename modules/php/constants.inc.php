<?php

/*
 * State constants
 */
define('ST_BGA_GAME_SETUP', 1);

const ST_NEW_ROUND = 10;
const ST_NEW_TURN = 15;

const ST_PLAYER_CHOOSE_CARD = 20;
const ST_PLAYER_PLAY_CARD = 30;
const ST_PLAYER_KEEP_CARD = 40;

const ST_NEXT_PLAYER = 70;
const ST_END_TURN = 75;
const ST_END_ROUND = 80;

define('ST_END_SCORE', 90);

define('ST_END_GAME', 99);
define('END_SCORE', 100);

/*
 * Constants
 */
define('END_ROUND_TYPE', 12); /* : */ define('STOP', 1); define('LAST_CHANCE', 2); define('EMPTY_DECK', 3);
define('LAST_CHANCE_CALLER', 13);
define('STOP_CALLER', 14);
define('BET_RESULT', 15);
const SELECTED_CARD_ID = 'SELECTED_CARD_ID';

/*
 * Cards
 */
// Category
define('MERMAID', 1);
define('PAIR', 2);
define('COLLECTION', 3);
define('MULTIPLIER', 4);
define('SPECIAL', 5);

// Pair family
define('CRAB', 1);
define('BOAT', 2);
define('FISH', 3);
define('SWIMMER', 4);
define('SHARK', 5);
define('JELLYFISH', 6);
// Collection family
define('SHELL', 1);
define('OCTOPUS', 2);
define('PENGUIN', 3);
define('SAILOR', 4);
// Multiplier family
define('LIGHTHOUSE', 1);
define('SHOAL_FISH', 2);
define('PENGUIN_COLONY', 3);
define('CAPTAIN', 4);
define('CAST_CRAB', 5);
// Special
define('STARFISH', 1);
define('SEAHORSE', 2);

define('COLLECTION_POINTS', [
    SHELL => [0, 2, 4, 6, 8, 10],
    OCTOPUS => [0, 3, 6, 9, 12],
    PENGUIN => [1, 3, 5],
    SAILOR => [0, 5],
]);


/*
 * Colors
 */
define('WHITE', 0);
define('DARK_BLUE', 1);
define('LIGHT_BLUE', 2);
define('BLACK', 3);
define('YELLOW', 4);
define('GREEN', 5);
define('PURPLE', 6);
define('GREY', 7);
define('LIGHT_ORANGE', 8);
define('PINK', 9);
define('ORANGE', 10);

?>
