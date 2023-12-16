class Stacks {
    public deck: Deck<Card>;

    private discardStocks: Deck<Card>[] = [];
    private pickStock: LineStock<Card>;

    constructor(private game: PixiesGame, gamedatas: PixiesGamedatas) {
        [1, 2].forEach(number => {
            const discardDiv = document.getElementById(`discard${number}`);
            const cardNumber = gamedatas[`remainingCardsInDiscard${number}`];
            this.discardStocks[number] = new Deck<Card>(this.game.cardsManager, discardDiv, {
                autoUpdateCardNumber: false,
                cardNumber,
                topCard: gamedatas[`discardTopCard${number}`],
                counter: {
                    extraClasses: 'pile-counter',
                }
            });
            discardDiv.addEventListener('click', () => this.game.onDiscardPileClick(number));
            // this.discardStocks[number].onCardClick = () => this.game.onDiscardPileClick(number);
        });
        
        this.pickStock = new LineStock<Card>(this.game.cardsManager, document.getElementById('pick'), {
            gap: '0px',
        });
        this.pickStock.onCardClick = card => this.game.onCardClick(card);

        this.deck = new Deck<Card>(this.game.cardsManager, document.getElementById('deck'), {
            topCard: gamedatas.deckTopCard,
            cardNumber: gamedatas.remainingCardsInDeck,
            counter: {
                extraClasses: 'pile-counter',
            }
        });
        this.deck.onCardClick = () => this.game.takeCardsFromDeck();
    }
    
    public makeDeckSelectable(selectable: boolean) {
        this.deck.setSelectionMode(selectable ? 'single' : 'none');
    }

    public makeDiscardSelectable(selectable: boolean) {
        [1, 2].forEach(number => this.discardStocks[number].setSelectionMode(selectable ? 'single' : 'none'));
    }

    public makePickSelectable(selectable: boolean) {
        this.pickStock.setSelectionMode(selectable ? 'single' : 'none');
    }
    
    public showPickCards(show: boolean, cards?: Card[], currentPlayer: boolean = false) {
        document.getElementById('pick').dataset.visible = show.toString();

        cards?.forEach(card => {
            if (document.getElementById(`ssp-card-${card.id}`)?.parentElement?.id !== 'pick') {
                this.pickStock.addCard(card, {
                    fromElement: document.getElementById('deck')
                }, {
                    visible: false, // start hidden
                });
                // set card informations
                if (currentPlayer) {
                    this.pickStock.setCardVisible(card, true, { updateData: true, updateFront: true, updateBack: true, });
                }
            }
        });
        this.game.updateTableHeight();
    }
    
    public setDiscardCard(discardNumber: number, card: Card | null, newCount: number | null = null, from: HTMLElement = undefined) {
        if (card) {
            this.discardStocks[discardNumber].addCard(card, { fromElement: from });
            this.discardStocks[discardNumber].setCardVisible(card, true);
        }
        if (newCount !== null) {
            this.discardStocks[discardNumber].setCardNumber(newCount, card);
        }
    }
    
    public getDiscardCards(): Card[] {
        return [1, 2].map(discardNumber => this.discardStocks[discardNumber].getCards()).flat();
    }
    
    public cleanDiscards() {
        [1, 2].forEach(discardNumber => this.discardStocks[discardNumber].setCardNumber(0));
    }
    
    public getDiscardDeck(discardNumber: number): CardStock<Card> {
        return this.discardStocks[discardNumber];
    }
}