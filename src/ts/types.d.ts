/**
 * Your game interfaces
 */

interface Card {
    id: number;
    location: string;
    locationArg: number;
    type: number;
    colors: number[];
    index: number;
    value: number;
    spirals: number;
    crosses: number;
}

interface DetailledScore {
    validatedCardPoints: number;
    largestColorZonePoints: number;
    spiralsAndCrossesPoints: number;
    facedownCardsPoints: number;
    points: number;
}

interface PixiesPlayer extends Player {
    playerNo: number;
    cards: { [slot: number]: Card[] };
}

interface PixiesGamedatas extends Gamedatas<PixiesPlayer> {
    // Add here variables you set up in getAllDatas
    remainingCardsInDeck: number;
    tableCards: Card[];
    roundResult: { [playerId: number]: DetailledScore }[];
    roundNumber: number;
    lastTurn: boolean;
    flowerPowerExpansion: boolean;
}

interface EnteringChooseCardArgs {
}

interface EnteringPlayCardArgs {
    selectedCard: Card;
    spaces: number[];
}

interface EnteringKeepCardArgs {
    selectedCard: Card;
    cards: Card[];
}

interface NotifNewRoundArgs {
    round: number;
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
    detailledScore: DetailledScore;
    round: number;
}

interface NotifRoundResultArgs {
    roundResult: { [playerId: number]: DetailledScore };
    round: number;
}

interface NotifEndRoundArgs {
    remainingCardsInDeck: number;
}
