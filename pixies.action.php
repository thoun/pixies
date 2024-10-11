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

    public function chooseCard() {
        self::setAjaxMode();

        $id = self::getArg("id", AT_posint, true);
        $autoplace = self::getArg("autoplace", AT_bool, false);

        $this->game->chooseCard($id, $autoplace);

        self::ajaxResponse();
    }

    public function playCard() {
        self::setAjaxMode();

        $space = self::getArg("space", AT_posint, true);

        $this->game->playCard($space);

        self::ajaxResponse();
    }

    public function keepCard() {
        self::setAjaxMode();

        $index = self::getArg("index", AT_posint, true);

        $this->game->keepCard($index);

        self::ajaxResponse();
    }

    public function seen() {
        self::setAjaxMode();

        $this->game->seen();

        self::ajaxResponse();
    }

    public function cancel() {
        self::setAjaxMode();

        $this->game->cancel();

        self::ajaxResponse();
    }
}