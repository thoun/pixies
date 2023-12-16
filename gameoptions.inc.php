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
 * gameoptions.inc.php
 *
 * Pixies game options description
 * 
 * In this file, you can define your game options (= game variants).
 *   
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in pixies.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

require_once("modules/php/constants.inc.php");

$game_options = [

    EXPANSION => [
        'name' => totranslate('Extra Salt expansion'),
        'values' => [
            1 => [
                'name' => totranslate('Disabled'),
            ],
            2 => [
                'name' => totranslate('Enabled'),
                'tmdisplay' => totranslate('Extra Salt expansion'),
                'description' => totranslate('An additional set of 8 cards, including 5 new effects'),
                'nobeginner' => true,
            ],
        ],
        'default' => 1,
    ],

    DOUBLE_POINTS => [
        'name' => totranslate('Double points'),
        'values' => [
            1 => [
                'name' => totranslate('Disabled'),
            ],
            2 => [
                'name' => totranslate('Enabled'),
                'tmdisplay' => totranslate('Double points'),
                'description' => totranslate('Points to end the game is 80/70/60 instead of 40/35/30'),
                'nobeginner' => true,
            ],
        ],
        'default' => 1,
    ],
];

$game_preferences = [    
    202 => [
        'name' => totranslate('Countdown timer when no action is possible'),
        'needReload' => false,
        'values' => [
            1 => ['name' => totranslate('Enabled')],
            2 => ['name' => totranslate('Disabled')],
        ],
        'default' => 1,
    ],
];


