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
                <div id="player-table-${this.playerId}-cards-${i}" class="space" style="--value: '${i}';"></div>`;
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
    
    public async playCard(card: Card, space: number): Promise<any> {
        await this.tableCards[space].addCard(card);
    }
    
    public async keepCard(hiddenCard: Card, visibleCard: Card, space: number): Promise<any> {
        this.game.cardsManager.updateCardInformations(hiddenCard);
        await this.tableCards[space].addCard(hiddenCard);
        
        this.game.cardsManager.updateCardInformations(visibleCard);
        await this.tableCards[space].addCard(visibleCard);
    }
    
    public setSelectableSpaces(spaces: number[]) {
        for (let i = 1; i <= 9; i++) {
            document.getElementById(`player-table-${this.playerId}-cards-${i}`).classList.toggle('selectable', spaces.includes(i));
        }
    }
}