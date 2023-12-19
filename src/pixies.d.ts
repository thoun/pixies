/**
 * Your game interfaces
 */

interface Card {
    id: number;
    location: string;
    locationArg: number;
    color: number;
    index: number;
    value: number;
    spirals: number;
    crosses: number;
}

interface PixiesPlayer extends Player {
    playerNo: number;
    cards: { [slot: number]: Card[] };
}

interface PixiesGamedatas {
    current_player_id: string;
    decision: {decision_type: string};
    game_result_neutralized: string;
    gamestate: Gamestate;
    gamestates: { [gamestateId: number]: Gamestate };
    neutralized_player_id: string;
    notifications: {last_packet_id: string, move_nbr: string}
    playerorder: (string | number)[];
    players: { [playerId: number]: PixiesPlayer };
    tablespeed: string;

    // Add here variables you set up in getAllDatas
    remainingCardsInDeck: number;
    tableCards: Card[];
}

interface PixiesGame extends Game {
    animationManager: AnimationManager;
    cardsManager: CardsManager;

    getPlayerId(): number;
    getPlayerColor(playerId: number): string;

    updateTableHeight(): void;
    setTooltip(id: string, html: string): void;
    onTableCardClick(card: Card): void;
    onSpaceClick(space: number): void;
}

interface EnteringChooseCardArgs {
}

interface EnteringPlayCardArgs {
    spaces: number[];
}

interface NotifCardInDiscardFromDeckArgs {
    card: Card;
    discardId: number;
    deckTopCard?: Card;
    remainingCardsInDeck: number;
}

interface NotifCardInHandFromDiscardArgs {
    playerId: number;
    card: Card;
    discardId: number;
    newDiscardTopCard: Card | null;
    remainingCardsInDiscard: number;
}

interface NotifCardInHandFromPickArgs {
    playerId: number;
    card?: Card;
    deckTopCard?: Card;
    remainingCardsInDeck: number;
}

interface NotifCardInDiscardFromPickArgs {
    playerId: number;
    card: Card;
    discardId: number;
    remainingCardsInDiscard: number;
}

interface NotifCardsInDeckFromPickArgs {
    playerId: number;
    cards: Card[];
    deckTopCard?: Card;
    remainingCardsInDeck: number;
}

interface NotifScoreArgs {
    playerId: number;
    newScore: number;
    incScore: number;
}

interface NotifPlayCardArgs {
    playerId: number;
    cards: Card[];
}

interface NotifRevealHandArgs extends NotifPlayCardArgs {
    playerPoints: number;
}

interface NotifAnnounceEndRoundArgs {
    playerId: number;
    announcement: string;
}

interface NotifEndRoundArgs {
    deckTopCard?: Card;
    remainingCardsInDeck: number;
}

interface NotifBetResultArgs {
    playerId: number;
    result: string;
}

interface NotifUpdateCardsPointsArgs {
    cardsPoints: number;
    detailledPoints: number[];
}

interface NotifReshuffleDeckArgs {
    deckTopCard: Card;
}
