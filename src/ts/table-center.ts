import { Game } from "./Game";
import { BgaCards } from "./libs";

export class TableCenter {
    // @ts-ignore
    public deck:  BgaCards.Deck<Card>;
    // @ts-ignore
    private tableCards:  BgaCards.LineStock<Card>;

    constructor(private game: Game, gamedatas: PixiesGamedatas) {
        const tableCardsDiv = document.getElementById(`table-cards`);
        this.tableCards = new BgaCards.LineStock/*<Card>*/(this.game.cardsManager, tableCardsDiv);
        this.tableCards.onCardClick = card => this.game.onTableCardClick(card);
        this.tableCards.addCards(gamedatas.tableCards);

        this.deck = new BgaCards.Deck/*<Card>*/(this.game.cardsManager, document.getElementById('deck'), {
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