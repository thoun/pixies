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
    spirals: number;
    crosses: number;
    row?: number;
    column?: number;
    flipped?: boolean;
    rowEffect?: number;
    columnEffect?: number;
}

interface DetailledScore {
    validatedCardPoints: number;
    largestColorZonePoints: number;
    spiralsAndCrossesPoints: number;
    facedownCardsPoints: number;
    points: number;
    computedSpiralsPerCard?: { [cardId: number] : number};
    computedCrossesPerCard?: { [cardId: number] : number};
    largestColorZoneColor: number;
    largestColorZoneCardCoordinates: number[][];
}

interface PixiesPlayer extends Player {
    playerNo: number;
    cards: Card[];
}

interface PixiesGamedatas extends Gamedatas<PixiesPlayer> {
    // Add here variables you set up in getAllDatas
    remainingCardsInDeck: number;
    tableCards: Card[];
    roundResult: { [playerId: number]: DetailledScore }[];
    roundNumber: number;
    lastTurn: boolean;
    flowerPowerExpansion: boolean;
    littleGiantsExpansion: boolean;
}

interface EnteringChooseCardArgs {
}

interface EnteringPlayCardArgs {
    selectedCard: Card;
    spaces: string[];
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
    row: number;
    column: number;
}

interface NotifKeepCardArgs {
    playerId: number;
    hiddenCard: Card;
    visibleCard: Card;
    space: number;
    row: number;
    column: number;
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
