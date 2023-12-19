class TableCenter {
    public deck: Deck<Card>;

    private tableCards: LineStock<Card>;

    constructor(private game: PixiesGame, gamedatas: PixiesGamedatas) {
        const tableCardsDiv = document.getElementById(`table-cards`);
        this.tableCards = new LineStock<Card>(this.game.cardsManager, tableCardsDiv);
        this.tableCards.onCardClick = card => this.game.onTableCardClick(card);
        this.tableCards.addCards(gamedatas.tableCards);

        this.deck = new Deck<Card>(this.game.cardsManager, document.getElementById('deck'), {
            cardNumber: gamedatas.remainingCardsInDeck,
            /*counter: {
                extraClasses: 'pile-counter',
            }*/
        });
    }

    public makeCardsSelectable(selectable: boolean) {
        this.tableCards.setSelectionMode(selectable ? 'single' : 'none');
    }
}