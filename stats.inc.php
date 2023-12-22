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
 * stats.inc.php
 *
 * Pixies game statistics description
 *
 */

/*
    In this file, you are describing game statistics, that will be displayed at the end of the
    game.
    
    !! After modifying this file, you must use "Reload  statistics configuration" in BGA Studio backoffice
    ("Control Panel" / "Manage Game" / "Your Game")
    
    There are 2 types of statistics:
    _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
    _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

    Statistics types can be "int" for integer, "float" for floating point values, and "bool" for boolean
    
    Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
    in your game logic, using statistics names defined below.
    
    !! It is not a good idea to modify this file when a game is running !!

    If your game is already public on BGA, please read the following before any change:
    http://en.doc.boardgamearena.com/Post-release_phase#Changes_that_breaks_the_games_in_progress
    
    Notes:
    * Statistic index is the reference used in setStat/incStat/initStat PHP method
    * Statistic index must contains alphanumerical characters and no space. Example: 'turn_played'
    * Statistics IDs must be >=10
    * Two table statistics can't share the same ID, two player statistics can't share the same ID
    * A table statistic can have the same ID than a player statistics
    * Statistics ID is the reference used by BGA website. If you change the ID, you lost all historical statistic data. Do NOT re-use an ID of a deleted statistic
    * Statistic name is the English description of the statistic as shown to players
    
*/


$commonStats = [
    "turnsNumber" => [
        "id" => 11,
        "name" => totranslate("Number of turns"),
        "type" => "int" 
    ],
    "takeCardFromDeck" => [
        "id" => 12,
        "name" => totranslate("Cards taken from deck"),
        "type" => "int"
    ],
    "takeFromDiscard" => [
        "id" => 13,
        "name" => totranslate("Cards taken from discard"),
        "type" => "int"
    ],
    "playedDuoCards" => [
        "id" => 14,
        "name" => totranslate("Played duo cards"),
        "type" => "int"
    ],
    "playedDuoCards1" => [
        "id" => 15,
        "name" => totranslate("Played duo crab cards"),
        "type" => "int"
    ],
    "playedDuoCards2" => [
        "id" => 16,
        "name" => totranslate("Played duo boat cards"),
        "type" => "int"
    ],
    "playedDuoCards3" => [
        "id" => 17,
        "name" => totranslate("Played duo fish cards"),
        "type" => "int"
    ],
    "playedDuoCards4" => [
        "id" => 18,
        "name" => totranslate("Played duo swimmer/shark cards"),
        "type" => "int"
    ],
    "winWithMermaids" => [
        "id" => 19,
        "name" => totranslate("Win with 4 Mermaids"),
        "type" => "bool"
    ],
    "lastChanceBetWon" => [
        "id" => 23,
        "name" => totranslate("Last chance bet won"),
        "type" => "int"
    ],
    "lastChanceBetLost" => [
        "id" => 24,
        "name" => totranslate("Last chance bet lost"),
        "type" => "int"
    ],
    "cardsCollected2" => [
        "id" => 25,
        "name" => totranslate("Duo cards collected"),
        "type" => "int"
    ],
    "cardsCollected1" => [
        "id" => 26,
        "name" => totranslate("Mermaid cards collected"),
        "type" => "int"
    ],
    "cardsCollected3" => [
        "id" => 27,
        "name" => totranslate("Collector cards collected"),
        "type" => "int"
    ],
    "cardsCollected4" => [
        "id" => 28,
        "name" => totranslate("Point Multiplier cards collected"),
        "type" => "int"
    ],
    "playedDuoCards5" => [
        "id" => 29,
        "name" => totranslate("Played duo swimmer/jellyfish cards"),
        "type" => "int"
    ],
    "playedDuoCards6" => [
        "id" => 30,
        "name" => totranslate("Played duo crab/lobster cards"),
        "type" => "int"
    ],
];

$stats_type = [

    // Statistics global to table
    "table" => $commonStats + [
        "roundNumber" => [
            "id" => 10,
            "name" => totranslate("Round number"),
            "type" => "int"
        ], 
    ],
    
    // Statistics existing for each player
    "player" => $commonStats + [
    ]
];
