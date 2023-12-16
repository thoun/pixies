<?php

trait ActionTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    
    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in nicodemus.action.php)
    */

    public function takeCardsFromDeck() {
        $this->checkAction('takeCardsFromDeck'); 
        
        $playerId = intval($this->getActivePlayerId());
        $args = $this->argTakeCards();

        if ($args['forceTakeOne']) {
            $this->takeFirstCardFromDeck($playerId);
            return;
        }

        if (!$args['canTakeFromDeck']) {
            throw new BgaUserException("You can't take a card from the deck");
        }

        $cards = $this->getCardsFromDb($this->cards->pickCardsForLocation(2, 'deck', 'pick'));

        self::notifyAllPlayers('log', clienttranslate('${player_name} picks ${number} cards from the deck'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'number' => count($cards),
            'preserve' => ['actionPlayerId'],
            'actionPlayerId' => $playerId,
        ]);

        $this->incStat(1, 'takeCardFromDeck');
        $this->incStat(1, 'takeCardFromDeck', $playerId);

        $this->gamestate->nextState('chooseCard');
    }

    public function takeFirstCardFromDeck(int $playerId) {

        if (intval($this->cards->countCardInLocation('deck')) > 0) {
            $card = $this->getCardFromDb($this->cards->pickCardForLocation('deck', 'hand'.$playerId));
            $this->cardCollected($playerId, $card);

            self::notifyPlayer($playerId, 'cardInHandFromDeck', clienttranslate('You take ${cardColor} ${cardName} card from deck'), [
                'playerId' => $playerId,
                'player_name' => $this->getPlayerName($playerId),
                'card' => $card,
                'cardName' => $this->getCardName($card),
                'cardColor' => $this->COLORS[$card->color],
                'i18n' => ['cardName', 'cardColor'],
                'preserve' => ['actionPlayerId'],
                'actionPlayerId' => $playerId,
                'deckTopCard' => $this->getDeckTopCard(),
                'remainingCardsInDeck' => $this->getRemainingCardsInDeck(),
            ]);
            self::notifyAllPlayers('cardInHandFromDeck', clienttranslate('${player_name} took a card from deck'), [
                'playerId' => $playerId,
                'player_name' => $this->getPlayerName($playerId),
                'card' => Card::onlyId($card),
                'preserve' => ['actionPlayerId'],
                'actionPlayerId' => $playerId,
                'deckTopCard' => $this->getDeckTopCard(),
                'remainingCardsInDeck' => $this->getRemainingCardsInDeck(),
            ]);

            $this->updateCardsPoints($playerId);
        }
            
        $this->gamestate->nextState('zombiePass');

    }

    public function takeCardFromDiscard(int $discardNumber) {
        $this->checkAction('takeCardFromDiscard'); 

        $args = $this->argTakeCards();        
        
        if (!in_array($discardNumber, $args['canTakeFromDiscard'])) {
            throw new BgaUserException("You can't take a card from the discard pile");
        }

        $card = $this->getCardFromDb($this->cards->getCardOnTop('discard'.$discardNumber));
        if ($card == null) {
            throw new BgaUserException("No card in that discard");
        }

        $playerId = intval($this->getActivePlayerId());

        $this->cards->moveCard($card->id, 'hand'.$playerId);
        $this->cardCollected($playerId, $card);

        self::notifyAllPlayers('cardInHandFromDiscard', clienttranslate('${player_name} takes ${cardColor} ${cardName} from discard pile ${discardNumber}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $card,
            'cardName' => $this->getCardName($card),
            'cardColor' => $this->COLORS[$card->color],
            'i18n' => ['cardName', 'cardColor'],
            'discardId' => $discardNumber,
            'discardNumber' => $discardNumber,
            'newDiscardTopCard' => $this->getCardFromDb($this->cards->getCardOnTop('discard'.$discardNumber)),
            'remainingCardsInDiscard' => $this->getRemainingCardsInDiscard($discardNumber),
            'preserve' => ['actionPlayerId'],
            'actionPlayerId' => $playerId,
        ]);

        $this->incStat(1, 'takeFromDiscard');
        $this->incStat(1, 'takeFromDiscard', $playerId);

        $this->updateCardsPoints($playerId);
        $this->gamestate->nextState('playCards');
    }

    public function chooseCard(int $cardId) {
        $this->checkAction('chooseCard'); 
        
        $playerId = intval($this->getActivePlayerId());

        $card = $this->getCardFromDb($this->cards->getCard($cardId));
        if ($card->location != 'pick') {
            throw new BgaUserException("Cannot pick this card");
        }

        $this->cards->moveCard($card->id, 'hand'.$playerId);
        $this->cardCollected($playerId, $card);

        self::notifyPlayer($playerId, 'cardInHandFromPick', clienttranslate('You choose ${cardColor} ${cardName} card'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $card,
            'cardName' => $this->getCardName($card),
            'cardColor' => $this->COLORS[$card->color],
            'i18n' => ['cardName', 'cardColor'],
            'preserve' => ['actionPlayerId'],
            'actionPlayerId' => $playerId,
        ]);
        self::notifyAllPlayers('cardInHandFromPick', clienttranslate('${player_name} chooses a card'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => Card::onlyId($card),
            'preserve' => ['actionPlayerId'],
            'actionPlayerId' => $playerId,
        ]);

        $this->updateCardsPoints($playerId);
        
        $remainingCardsInPick = intval($this->cards->countCardInLocation('pick'));
        if ($remainingCardsInPick == 0) {
            $this->gamestate->nextState('playCards');
            return;
        }

        if (boolval($this->getGameStateValue(LOBSTER_POWER))) {
            $this->setGameStateValue(LOBSTER_POWER, 0);

            $cards = $this->getCardsFromDb($this->cards->getCardsInLocation('pick'));
            if (count($cards) > 0) {
                $this->cards->moveAllCardsInLocation('pick', 'deck');
                $this->cards->shuffle('deck');

                self::notifyAllPlayers('cardsInDeckFromPick', '', [
                    'playerId' => $playerId,
                    'player_name' => $this->getPlayerName($playerId),
                    'cards' => $cards,
                    'deckTopCard' => $this->getDeckTopCard(),
                    'remainingCardsInDeck' => $this->getRemainingCardsInDeck(),
                    'preserve' => ['actionPlayerId'],
                    'actionPlayerId' => $playerId,
                ]);

                self::notifyAllPlayers('reshuffleDeck', '', [
                    'deckTopCard' => $this->getDeckTopCard(),
                ]);
            }

            $this->gamestate->nextState('playCards');
        } else {
            $remainingCardsInDiscard1 = intval($this->cards->countCardInLocation('discard1'));
            $remainingCardsInDiscard2 = intval($this->cards->countCardInLocation('discard2'));

            if ($remainingCardsInDiscard1 == 0) {
                $this->applyPutDiscardPile(1);
                $this->gamestate->nextState('playCards');
            } else if ($remainingCardsInDiscard2 == 0) {
                $this->applyPutDiscardPile(2);
                $this->gamestate->nextState('playCards');
            } else {
                $this->gamestate->nextState('putDiscardPile');
            }
        }
    }

    private function applyPutDiscardPile(int $discardNumber) {        
        $playerId = intval($this->getActivePlayerId());

        $card = $this->getCardsFromDb($this->cards->getCardsInLocation('pick'))[0];
        if ($card == null) {
            throw new BgaUserException("No card in pick");
        }

        $location = 'discard'.$discardNumber;
        $maxLocationArg = intval($this->getUniqueValueFromDB("SELECT max(card_location_arg) FROM card where `card_location` = '$location'"));
        $this->cards->moveCard($card->id, $location, $maxLocationArg + 1);

        self::notifyAllPlayers('cardInDiscardFromPick', clienttranslate('${player_name} puts ${cardColor} ${cardName} to discard pile ${discardNumber}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => $card,
            'cardName' => $this->getCardName($card),
            'cardColor' => $this->COLORS[$card->color],
            'i18n' => ['cardName', 'cardColor'],
            'discardId' => $discardNumber,
            'discardNumber' => $discardNumber,
            'remainingCardsInDiscard' => $this->getRemainingCardsInDiscard($discardNumber),
            'preserve' => ['actionPlayerId'],
            'actionPlayerId' => $playerId,
        ]);
    }

    public function putDiscardPile(int $discardNumber) {
        $this->checkAction('putDiscardPile'); 
        
        if (!in_array($discardNumber, [1, 2])) {
            throw new BgaUserException("Invalid discard number");
        }

        $this->applyPutDiscardPile($discardNumber);

        $this->gamestate->nextState('playCards');
    }

    public function playCards(int $id1, int $id2) {
        $this->checkAction('playCards'); 

        if ($id1 == $id2) {
            throw new BgaUserException("Same id");
        }

        $playerId = intval($this->getActivePlayerId());
        $cards = $this->getCardsFromDb($this->cards->getCards([$id1, $id2]));

        if ($this->array_some($cards, fn($card) => $card->location != 'hand'.$playerId || $card->category != PAIR)) {
            throw new BgaUserException("You must select Pair cards from your hand");
        }

        if (!in_array($cards[1]->family, $cards[0]->matchFamilies)) {
            throw new BgaUserException("Invalid pair");
        }

        $count = intval($this->cards->countCardInLocation('table'.$playerId));
        foreach($cards as $card) {
            $this->cards->moveCard($card->id, 'table'.$playerId, ++$count);
        }

        $families = array_map(fn($card) => $card->family, $cards);
        sort($families);
        $action = '';
        $power = 0;
        switch ($families[0]) {
            case CRAB:
                if ($families[1] == LOBSTER) {
                    $action = clienttranslate('takes the first five cards from the deck and keeps one of them');
                    $power = 6;
                } else {
                    $action = clienttranslate('takes a card from a discard pile');
                    $power = 1;
                }
                break;
            case BOAT:
                $action = clienttranslate('plays a new turn');
                $power = 2;
                break;
            case FISH:
                $action = clienttranslate('adds the top card from the deck to hand');
                $power = 3;
                break;
            case SWIMMER:
            case SHARK:
                if ($families[0] == SWIMMER && $families[1] == JELLYFISH) {
                    $action = clienttranslate('forces the opposing players to only draw the first card from the deck on their next turn');
                    $power = 5;
                } else {
                    $action = clienttranslate('steals a random card from another player');
                    $power = 4;
                }
                break;
        }

        self::notifyAllPlayers('playCards', clienttranslate('${player_name} plays cards ${cardColor1} ${cardName1} and ${cardColor2} ${cardName2} and ${action}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'cards' => $cards,
            'cardName1' => $this->getCardName($cards[0]),
            'cardName2' => $this->getCardName($cards[1]),
            'cardColor1' => $this->COLORS[$cards[0]->color],
            'cardColor2' => $this->COLORS[$cards[1]->color],
            'action' => $action,
            'i18n' => ['cardName1', 'cardName2', 'cardColor1', 'cardColor2', 'action'],
            'preserve' => ['actionPlayerId'],
            'actionPlayerId' => $playerId,
        ]);

        $this->incStat(1, 'playedDuoCards');
        $this->incStat(1, 'playedDuoCards', $playerId);
        $this->incStat(1, 'playedDuoCards'.$power);
        $this->incStat(1, 'playedDuoCards'.$power, $playerId);

        switch ($power) {
            case 1:
                if ((intval($this->cards->countCardInLocation('discard1')) + intval($this->cards->countCardInLocation('discard2'))) > 0) {
                    $this->gamestate->nextState('chooseDiscardPile');
                } else {
                    self::notifyAllPlayers('log', clienttranslate('Impossible to activate Pair effect, it is ignored'), []);
                    $this->gamestate->nextState('playCards');
                }
                break;
            case 2:
                if ((intval($this->cards->countCardInLocation('deck')) + intval($this->cards->countCardInLocation('discard1')) + intval($this->cards->countCardInLocation('discard2'))) > 0) {
                    $this->gamestate->nextState('newTurn');
                } else {
                    self::notifyAllPlayers('log', clienttranslate('Impossible to activate Pair effect, it is ignored'), []);
                    $this->gamestate->nextState('playCards');
                }
                break;
            case 3:
                if (intval($this->cards->countCardInLocation('deck')) > 0) {
                    $card = $this->getCardFromDb($this->cards->pickCardForLocation('deck', 'hand'.$playerId));
                    $this->cardCollected($playerId, $card);

                    self::notifyPlayer($playerId, 'cardInHandFromDeck', clienttranslate('You take ${cardColor} ${cardName} card from deck'), [
                        'playerId' => $playerId,
                        'player_name' => $this->getPlayerName($playerId),
                        'card' => $card,
                        'cardName' => $this->getCardName($card),
                        'cardColor' => $this->COLORS[$card->color],
                        'i18n' => ['cardName', 'cardColor'],
                        'preserve' => ['actionPlayerId'],
                        'actionPlayerId' => $playerId,
                        'deckTopCard' => $this->getDeckTopCard(),
                        'remainingCardsInDeck' => $this->getRemainingCardsInDeck(),
                    ]);
                    self::notifyAllPlayers('cardInHandFromDeck', clienttranslate('${player_name} took a card from deck'), [
                        'playerId' => $playerId,
                        'player_name' => $this->getPlayerName($playerId),
                        'card' => Card::onlyId($card),
                        'preserve' => ['actionPlayerId'],
                        'actionPlayerId' => $playerId,
                        'deckTopCard' => $this->getDeckTopCard(),
                        'remainingCardsInDeck' => $this->getRemainingCardsInDeck(),
                    ]);
                    
                    $this->updateCardsPoints($playerId);
                    $this->gamestate->nextState('playCards');
                } else {
                    self::notifyAllPlayers('log', clienttranslate('Impossible to activate Pair effect, it is ignored'), []);
                    $this->gamestate->nextState('playCards');
                }
                
                break;
            case 4:
                $possibleOpponentsToSteal = $this->getPossibleOpponentsToSteal($playerId);

                if (count($possibleOpponentsToSteal) > 0) {
                    $this->gamestate->nextState('chooseOpponent');
                } else {
                    self::notifyAllPlayers('log', clienttranslate('Impossible to activate Pair effect, it is ignored'), []);
                    $this->gamestate->nextState('playCards');
                }

                /*if (count($possibleOpponentsToSteal) > 1) {
                    $this->gamestate->nextState('chooseOpponent');
                } else {
                    if (count($possibleOpponentsToSteal) == 1) {
                        $this->applySteal($playerId, $possibleOpponentsToSteal[0]);
                    } else {
                        self::notifyAllPlayers('log', clienttranslate('Impossible to activate Pair effect, it is ignored'), []);
                    }
                    $this->gamestate->nextState('playCards');
                }*/
                break;
            case 5:
                $this->setGameStateValue(FORCE_TAKE_ONE, $playerId);
                $this->gamestate->nextState('playCards');
                break;
            case 6:
                if (intval($this->cards->countCardInLocation('deck')) > 0) {
                    $this->setGameStateValue(LOBSTER_POWER, 1);

                    $cards = $this->getCardsFromDb($this->cards->pickCardsForLocation(5, 'deck', 'pick'));
            
                    self::notifyAllPlayers('log', clienttranslate('${player_name} picks ${number} cards from the deck'), [
                        'playerId' => $playerId,
                        'player_name' => $this->getPlayerName($playerId),
                        'number' => count($cards),
                        'preserve' => ['actionPlayerId'],
                        'actionPlayerId' => $playerId,
                    ]);

                    $this->gamestate->nextState('chooseCard');
                } else {
                    self::notifyAllPlayers('log', clienttranslate('Impossible to activate Pair effect, it is ignored'), []);
                    $this->gamestate->nextState('playCards');
                }
                break;
        }
    }

    public function playCardsTrio(int $id1, int $id2, int $idStarfish) {
        $this->checkAction('playCardsTrio'); 

        if ($id1 == $id2) {
            throw new BgaUserException("Same id");
        }

        $playerId = intval($this->getActivePlayerId());
        $cards = $this->getCardsFromDb($this->cards->getCards([$id1, $id2]));

        if ($this->array_some($cards, fn($card) => $card->location != 'hand'.$playerId || $card->category != PAIR)) {
            throw new BgaUserException("You must select Pair cards from your hand");
        }

        if (!in_array($cards[1]->family, $cards[0]->matchFamilies)) {
            throw new BgaUserException("Invalid pair");
        }

        $starfishCard = $this->getCardFromDb($this->cards->getCard($idStarfish));
        if ($starfishCard->location != 'hand'.$playerId || $starfishCard->category != SPECIAL || $starfishCard->family != STARFISH) {
            throw new BgaUserException("You must select a Starfish card from your hand");
        }

        $allCards = array_merge($cards, [$starfishCard]);

        $count = intval($this->cards->countCardInLocation('table'.$playerId));
        foreach($allCards as $card) {
            $this->cards->moveCard($card->id, 'table'.$playerId, ++$count);
        }

        self::notifyAllPlayers('playCards', clienttranslate('${player_name} plays cards ${cardColor1} ${cardName1} and ${cardColor2} ${cardName2} with a ${cardColor3} ${cardName3}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'cards' => $allCards,
            'cardName1' => $this->getCardName($cards[0]),
            'cardName2' => $this->getCardName($cards[1]),
            'cardName3' => $this->getCardName($starfishCard),
            'cardColor1' => $this->COLORS[$cards[0]->color],
            'cardColor2' => $this->COLORS[$cards[1]->color],
            'cardColor3' => $this->COLORS[$starfishCard->color],
            'i18n' => ['cardName1', 'cardName2', 'cardName3', 'cardColor1', 'cardColor2', 'cardColor3'],
            'preserve' => ['actionPlayerId'],
            'actionPlayerId' => $playerId,
        ]);

        $this->updateCardsPoints($playerId);

        /*$this->incStat(1, 'playedDuoCards');
        $this->incStat(1, 'playedDuoCards', $playerId);*/ 
        
        $this->gamestate->nextState('playCards');
    }

    public function endTurn() {
        $this->checkAction('endTurn'); 

        $playerId = intval($this->getActivePlayerId());

        $mermaids = $this->getPlayerMermaids($playerId);
        if (count($mermaids) == 4) {
            $this->endGameWithMermaids($playerId);
            return;
        }
        
        $this->gamestate->nextState('endTurn');
    }

    private function applyEndRound(int $type, string $announcement) {
        $playerId = intval($this->getActivePlayerId());

        $mermaids = $this->getPlayerMermaids($playerId);
        if (count($mermaids) == 4) {
            $this->endGameWithMermaids($playerId);
            return;
        }

        $this->setGameStateValue(END_ROUND_TYPE, $type);

        self::notifyAllPlayers('announceEndRound', clienttranslate('${player_name} announces ${announcement}!'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'announcement' => $announcement,
            'i18n' => ['announcement'],
        ]);
        
        $this->gamestate->nextState('endTurn');
    }

    public function endRound() {
        $this->checkAction('endRound');

        $playerId = intval($this->getActivePlayerId());

        $this->incStat(1, 'announce');
        $this->incStat(1, 'announce', $playerId);
        $this->incStat(1, 'announceLastChance');
        $this->incStat(1, 'announceLastChance', $playerId);

        $this->applyEndRound(LAST_CHANCE, $this->ANNOUNCEMENTS[LAST_CHANCE]);
    }

    public function immediateEndRound() {
        $this->checkAction('immediateEndRound');

        $playerId = intval($this->getActivePlayerId());

        $this->incStat(1, 'announce');
        $this->incStat(1, 'announce', $playerId);
        $this->incStat(1, 'announceStop');
        $this->incStat(1, 'announceStop', $playerId);

        $this->setGameStateValue(STOP_CALLER, $playerId);

        $this->applyEndRound(STOP, $this->ANNOUNCEMENTS[STOP]);
    }

    public function chooseDiscardPile(int $discardNumber) {
        $this->checkAction('chooseDiscardPile'); 
        
        if (!in_array($discardNumber, [1, 2])) {
            throw new BgaUserException("Invalid discard number");
        }

        if (intval($this->cards->countCardInLocation('discard'.$discardNumber)) == 0) {
            throw new BgaUserException("No card in that discard");
        }

        $card = $this->getCardFromDb($this->cards->getCardOnTop('discard'.$discardNumber));
        if ($card == null) {
            throw new BgaUserException("No card in that discard");
        }

        $this->setGameStateValue(CHOSEN_DISCARD, $discardNumber);

        $this->gamestate->nextState('chooseCard');
    }

    public function endGameWithMermaids(/*int | null*/$playerId = null) {
        if ($playerId === null) {
            $playerId = intval($this->getActivePlayerId());
        }

        $mermaids = $this->getPlayerMermaids($playerId);
        if (count($mermaids) == 4) {
            $count = intval($this->cards->countCardInLocation('table'.$playerId));
            foreach($mermaids as $card) {
                $this->cards->moveCard($card->id, 'table'.$playerId, ++$count);
            }

            $this->notifyAllPlayers('playCards', '', [
                'playerId' => $playerId,
                'cards' => $mermaids,
            ]);

            $this->gamestate->nextState('mermaids');
        } else {
            throw new BgaUserException("You need the four Mermaids");
        }
    }

    public function chooseDiscardCard(int $cardId) {
        $this->checkAction('chooseDiscardCard');

        $card = $this->getCardFromDb($this->cards->getCard($cardId));
        $discardNumber = $this->getGameStateValue(CHOSEN_DISCARD);
        if ($card == null || $card->location != 'discard'.$discardNumber) {
            throw new BgaUserException("Invalid discard card");
        }

        $playerId = intval($this->getActivePlayerId());

        $this->cards->moveCard($card->id, 'hand'.$playerId);
        $this->cardCollected($playerId, $card);

        self::notifyPlayer($playerId, 'cardInHandFromDiscardCrab', clienttranslate('You take ${cardColor} ${cardName} from discard pile ${discardNumber}'), [
            'playerId' => $playerId,
            'card' => $card,
            'cardName' => $this->getCardName($card),
            'cardColor' => $this->COLORS[$card->color],
            'i18n' => ['cardName', 'cardColor'],
            'discardId' => $discardNumber,
            'discardNumber' => $discardNumber,
            'newDiscardTopCard' => $this->getCardFromDb($this->cards->getCardOnTop('discard'.$discardNumber)),
            'remainingCardsInDiscard' => $this->getRemainingCardsInDiscard($discardNumber),
            'preserve' => ['actionPlayerId'],
            'actionPlayerId' => $playerId,
        ]);
        self::notifyAllPlayers('cardInHandFromDiscardCrab', clienttranslate('${player_name} takes a card from discard pile ${discardNumber}'), [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'card' => Card::onlyId($card),
            'discardId' => $discardNumber,
            'discardNumber' => $discardNumber,
            'newDiscardTopCard' => $this->getCardFromDb($this->cards->getCardOnTop('discard'.$discardNumber)),
            'remainingCardsInDiscard' => $this->getRemainingCardsInDiscard($discardNumber),
            'preserve' => ['actionPlayerId'],
            'actionPlayerId' => $playerId,
        ]);

        $this->updateCardsPoints($playerId);
        $this->gamestate->nextState('playCards');
    }

    public function chooseOpponent(int $opponentId) {
        $this->checkAction('chooseOpponent');

        $playerId = intval($this->getActivePlayerId());

        $this->applySteal($playerId, $opponentId);

        $this->gamestate->nextState('playCards');
    }

    public function seen() {
        $this->checkAction('seen');

        $playerId = intval($this->getCurrentPlayerId());

        $this->gamestate->setPlayerNonMultiactive($playerId, 'endRound');
    }
}
