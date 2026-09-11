<?php

trait StateTrait {

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stBeforeEndRound() {
        $roundNumber = intval($this->getStat('roundNumber'));

        $scoreRound = $this->scoreRound();
        
        foreach ($scoreRound as $playerId => $detailledScore) {
            $this->incPlayerScore($playerId, $detailledScore->points, clienttranslate('${player_name} gains ${incScore} points in this round'), [                
                'detailledScore' => $detailledScore,
            ]);
        }
        $this->setGlobalVariable(ROUND_RESULT.$roundNumber, $scoreRound);
        $this->notify->all('roundResult', '', [
            'roundResult' => $scoreRound,
            'round' => $roundNumber,
        ]);

        $lastRound = $roundNumber >= 3;
        if ($lastRound) {
            $this->gamestate->nextState('endRound');
        } else {
            $this->gamestate->setAllPlayersMultiactive();
        }
    }

}
