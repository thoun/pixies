const isDebug = window.location.host == 'studio.boardgamearena.com' || window.location.hash.indexOf('debug') > -1;;
const log = isDebug ? console.log.bind(window.console) : function () { };

class PlayerTable {
    public playerId: number;

    private currentPlayer: boolean;

    private tableCards: SlotStock<Card>[] = [];

    constructor(private game: PixiesGame, player: PixiesPlayer) {
        this.playerId = Number(player.id);
        this.currentPlayer = this.playerId == this.game.getPlayerId();

        let html = `
        <div id="player-table-${this.playerId}" class="player-table" style="border-color: #${player.color};">
            <div class="name-wrapper">
                <span class="name" style="color: #${player.color};">${player.name}</span>
            </div>
            <div id="player-table-${this.playerId}-cards" class="player-cards">`;
        for (let i = 1; i <= 9; i++) {
            html += `
                <div id="player-table-${this.playerId}-cards-${i}" class="space"></div>`;
        }
        html += `
            </div>
        </div>`;
        document.getElementById('tables').insertAdjacentHTML('beforeend', html);

        const stockSettings: SlotStockSettings<Card> = {
            slotsIds: [0, 1],
            mapCardToSlot: card => card.locationArg,
        }

        for (let i = 1; i <= 9; i++) {
            const spaceDiv = document.getElementById(`player-table-${this.playerId}-cards-${i}`);
            spaceDiv.addEventListener('click', () => {
                if (spaceDiv.classList.contains('selectable')) {
                    this.game.onSpaceClick(i);
                }
            })
            this.tableCards[i] = new SlotStock<Card>(this.game.cardsManager, spaceDiv, stockSettings);
            this.tableCards[i].addCards(player.cards[i]);
        }
    }

    public getAllCards(): Card[] {
        return this.tableCards.getCards();
    }
    
    public setSelectableSpaces(spaces: number[]) {
        for (let i = 1; i <= 9; i++) {
            document.getElementById(`player-table-${this.playerId}-cards-${i}`).classList.toggle('selectable', spaces.includes(i));
        }
    }

    public updateDisabledPlayCard(selectedCards: Card[], selectedStarfishCards: Card[], playableDuoCardFamilies: number[]) {
        if (!(this.game as any).isCurrentPlayerActive()) {
            return;
        }

        const selectableCards = this.handCards.getCards().filter(card => {
            let disabled = false;
                if (playableDuoCardFamilies.includes(card.family)) {
                    if (selectedCards.length >= 2) {
                        disabled = !selectedCards.some(c => c.id == card.id);
                    } else if (selectedCards.length == 1) {
                        const matchFamilies = selectedCards[0].matchFamilies;
                        disabled = card.id != selectedCards[0].id && !matchFamilies.includes(card.family);
                    }
                } else {
                    disabled = true;
                }
            return !disabled;
        });
        
        this.handCards.setSelectableCards(selectableCards);
    }
}