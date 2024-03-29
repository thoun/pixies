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

    getTableCards(): Card[] {
        return this.tableCards.getCards();
    }
    
    public newTurn(cards: Card[]): Promise<any> {
        return this.tableCards.addCards(cards, {
            fromStock: this.deck,
        }, undefined, 250);
    }

    public makeCardsSelectable(selectable: boolean) {
        this.tableCards.setSelectionMode(selectable ? 'single' : 'none');
    }

    public setSelectedCard(selectedCard: Card) {
        this.game.cardsManager.getCardElement(selectedCard)?.classList.add('bga-cards_selected-card');
    }
}