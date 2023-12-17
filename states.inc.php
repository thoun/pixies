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
 * states.inc.php
 *
 * Pixies game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

require_once("modules/php/constants.inc.php");

$basicGameStates = [

    // The initial state. Please do not modify.
    ST_BGA_GAME_SETUP => [
        "name" => "gameSetup",
        "description" => clienttranslate("Game setup"),
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => [ "" => ST_NEW_ROUND ]
    ],
   
    // Final state.
    // Please do not modify.
    ST_END_GAME => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd",
    ],
];


$playerActionsGameStates = [

    ST_PLAYER_TAKE_CARDS => [
        "name" => "takeCards",
        "description" => clienttranslate('${actplayer} must choose a card to play'),
        "descriptionmyturn" => clienttranslate('${you} must choose a card to play'),
        "type" => "activeplayer",
        "args" => "argTakeCards",
        "updateGameProgression" => true,
        "possibleactions" => [ 
            "takeCard",
        ],
        "transitions" => [
            "playCards" => ST_PLAYER_PLAY_CARDS,
            "chooseCard" => ST_PLAYER_CHOOSE_CARD,
            "zombiePass" => ST_NEXT_PLAYER,
        ]
    ],

    ST_PLAYER_CHOOSE_CARD => [
        "name" => "chooseCard",
        "description" => clienttranslate('${actplayer} must choose a card to keep'),
        "descriptionmyturn" => clienttranslate('${you} must choose a card to keep'),
        "type" => "activeplayer",
        "args" => "argChooseCard",  
        "possibleactions" => [ 
            "chooseCard",
        ],
        "transitions" => [
            "putDiscardPile" => ST_PLAYER_PUT_DISCARD_PILE,
            "playCards" => ST_PLAYER_PLAY_CARDS,
            "zombiePass" => ST_NEXT_PLAYER,
        ]
    ],

    ST_PLAYER_PUT_DISCARD_PILE => [
        "name" => "putDiscardPile",
        "description" => clienttranslate('${actplayer} must choose a discard pile for the other card'),
        "descriptionmyturn" => clienttranslate('${you} must choose a discard pile for the other card'),
        "type" => "activeplayer",
        "args" => "argChooseCard",  
        "possibleactions" => [ 
            "putDiscardPile",
        ],
        "transitions" => [
            "playCards" => ST_PLAYER_PLAY_CARDS,
            "zombiePass" => ST_NEXT_PLAYER,
        ]
    ],

    ST_PLAYER_PLAY_CARDS => [
        "name" => "playCards",
        "description" => clienttranslate('${actplayer} may play cards duo'),
        "descriptionmyturn" => clienttranslate('${you} may play cards duo'),
        "type" => "activeplayer",    
        "args" => "argPlayCards",     
        "action" => "stPlayCards",
        "updateGameProgression" => true,
        "possibleactions" => [ 
            "playCards",
            "playCardsTrio",
            "endTurn",
            "endRound",
            "immediateEndRound",
        ],
        "transitions" => [
            "chooseDiscardPile" => ST_PLAYER_CHOOSE_DISCARD_PILE,
            "newTurn" => ST_PLAYER_TAKE_CARDS,
            "chooseOpponent" => ST_PLAYER_CHOOSE_OPPONENT,
            "chooseCard" => ST_PLAYER_CHOOSE_CARD,
            "playCards" => ST_PLAYER_PLAY_CARDS,
            "endTurn" => ST_NEXT_PLAYER,
            "mermaids" => ST_END_SCORE,
            "zombiePass" => ST_NEXT_PLAYER,
        ]
    ],

    ST_PLAYER_CHOOSE_DISCARD_PILE => [
        "name" => "chooseDiscardPile",
        "description" => clienttranslate('${actplayer} must choose a discard pile'),
        "descriptionmyturn" => clienttranslate('${you} must choose a discard pile'),
        "type" => "activeplayer",
        "possibleactions" => [ 
            "chooseDiscardPile",
        ],
        "transitions" => [
            "chooseCard" => ST_PLAYER_CHOOSE_DISCARD_CARD,
            "zombiePass" => ST_NEXT_PLAYER,
        ]
    ],

    ST_PLAYER_CHOOSE_DISCARD_CARD => [
        "name" => "chooseDiscardCard",
        "description" => clienttranslate('${actplayer} must choose a card'),
        "descriptionmyturn" => clienttranslate('${you} must choose a card'),
        "type" => "activeplayer", 
        "args" => "argChooseDiscardCard", 
        "possibleactions" => [ 
            "chooseDiscardCard",
        ],
        "transitions" => [
            "playCards" => ST_PLAYER_PLAY_CARDS,
            "zombiePass" => ST_NEXT_PLAYER,
        ]
    ],

    ST_PLAYER_CHOOSE_OPPONENT => [
        "name" => "chooseOpponent",
        "description" => clienttranslate('${actplayer} must choose a card to steal'),
        "descriptionmyturn" => clienttranslate('${you} must choose a card to steal'),
        "type" => "activeplayer",
        "args" => "argChooseOpponent", 
        "possibleactions" => [ 
            "chooseOpponent",
        ],
        "transitions" => [
            "playCards" => ST_PLAYER_PLAY_CARDS,
            "zombiePass" => ST_NEXT_PLAYER,
        ]
    ],
];

$gameGameStates = [

    ST_NEW_ROUND => [
        "name" => "newRound",
        "description" => "",
        "type" => "game",
        "action" => "stNewRound",
        "transitions" => [
            "start" => ST_NEW_TURN,
        ],
    ],

    ST_NEW_TURN => [
        "name" => "newTurn",
        "description" => "",
        "type" => "game",
        "action" => "stNewTurn",
        "transitions" => [
            "start" => ST_PLAYER_TAKE_CARDS,
        ],
    ],

    ST_NEXT_PLAYER => [
        "name" => "nextPlayer",
        "description" => "",
        "type" => "game",
        "action" => "stNextPlayer",
        "transitions" => [
            "newTurn" => ST_PLAYER_TAKE_CARDS, 
            "endRound" => ST_MULTIPLAYER_BEFORE_END_ROUND,
        ],
    ],
    
    ST_MULTIPLAYER_BEFORE_END_ROUND => [
        "name" => "beforeEndRound",
        "description" => clienttranslate('Some players are seeing end round result'),
        "descriptionmyturn" => clienttranslate('End round result'),
        "type" => "multipleactiveplayer",
        "action" => "stBeforeEndRound",
        "possibleactions" => [ "seen" ],
        "transitions" => [
            "endRound" => ST_END_ROUND,
            "endScore" => ST_END_SCORE,
        ],
    ],

    ST_END_ROUND => [
        "name" => "endRound",
        "description" => "",
        "type" => "game",
        "action" => "stEndRound",
        "transitions" => [
            "newRound" => ST_NEW_ROUND,
            "endScore" => ST_END_SCORE,
        ],
    ],

    ST_END_SCORE => [
        "name" => "endScore",
        "description" => "",
        "type" => "game",
        "action" => "stEndScore",
        "transitions" => [
            "endGame" => ST_END_GAME,
        ],
    ],
];
 
$machinestates = $basicGameStates + $playerActionsGameStates + $gameGameStates;