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

    setTooltip(id: string, html: string): void;
    onTableCardClick(card: Card): void;
    onSpaceClick(space: number): void;
}

interface EnteringChooseCardArgs {
}

interface EnteringPlayCardArgs {
    spaces: number[];
}

interface NotifNewTurnArgs {
    cards: Card[];
}

interface NotifPlayCardArgs {
    playerId: number;
    card: Card;
    space: number;
}

interface NotifKeepCardArgs {
    playerId: number;
    hiddenCard: Card;
    visibleCard: Card;
    space: number;
}

interface NotifScoreArgs {
    playerId: number;
    newScore: number;
    incScore: number;
}

interface NotifEndRoundArgs {
    remainingCardsInDeck: number;
}
