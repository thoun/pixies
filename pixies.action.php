<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Pixies implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * pixies.action.php
 *
 * Pixies main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/pixies/pixies/myAction.html", ...)
 *
 */
  
  
  class action_pixies extends APP_GameAction { 
    // Constructor: please do not modify
   	public function __default() {
  	    if( self::isArg( 'notifwindow') ) {
            $this->view = "common_notifwindow";
  	        $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
  	    } else {
            $this->view = "pixies_pixies";
            self::trace( "Complete reinitialization of board game" );
      }
  	} 
  	
  	// define your action entry points there

    public function takeCardsFromDeck() {
        self::setAjaxMode();

        $this->game->takeCardsFromDeck();

        self::ajaxResponse();
    }

    public function takeCard() {
        self::setAjaxMode();

        $id = self::getArg("id", AT_posint, true);

        $this->game->takeCard($id);

        self::ajaxResponse();
    }

    public function chooseCard() {
        self::setAjaxMode();

        $id = self::getArg("id", AT_posint, true);

        $this->game->chooseCard($id);

        self::ajaxResponse();
    }

    public function putDiscardPile() {
        self::setAjaxMode();

        $discardNumber = self::getArg("discardNumber", AT_posint, true);

        $this->game->putDiscardPile($discardNumber);

        self::ajaxResponse();
    }

    public function playCards() {
        self::setAjaxMode();

        $cardId1 = self::getArg("id1", AT_posint, true);
        $cardId2 = self::getArg("id2", AT_posint, true);

        $this->game->playCards($cardId1, $cardId2);

        self::ajaxResponse();
    }

    public function playCardsTrio() {
        self::setAjaxMode();

        $cardId1 = self::getArg("id1", AT_posint, true);
        $cardId2 = self::getArg("id2", AT_posint, true);
        $starfishCardId = self::getArg("starfishId", AT_posint, true);

        $this->game->playCardsTrio($cardId1, $cardId2, $starfishCardId);

        self::ajaxResponse();
    }

    public function endTurn() {
        self::setAjaxMode();

        $this->game->endTurn();

        self::ajaxResponse();
    }

    public function endRound() {
        self::setAjaxMode();

        $this->game->endRound();

        self::ajaxResponse();
    }

    public function immediateEndRound() {
        self::setAjaxMode();

        $this->game->immediateEndRound();

        self::ajaxResponse();
    }

    public function chooseDiscardPile() {
        self::setAjaxMode();

        $discardNumber = self::getArg("discardNumber", AT_posint, true);

        $this->game->chooseDiscardPile($discardNumber);

        self::ajaxResponse();
    }

    public function chooseDiscardCard() {
        self::setAjaxMode();

        $id = self::getArg("id", AT_posint, true);

        $this->game->chooseDiscardCard($id);

        self::ajaxResponse();
    }

    public function chooseOpponent() {
        self::setAjaxMode();

        $id = self::getArg("id", AT_posint, true);

        $this->game->chooseOpponent($id);

        self::ajaxResponse();
    }

    public function seen() {
        self::setAjaxMode();

        $this->game->seen();

        self::ajaxResponse();
    }
}