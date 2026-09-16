import { Game } from "./Game";
import { BgaCards } from "./libs";

const ANIMATION_MS = 500;

export class PlayerTable {
    public playerId: number;

    private currentPlayer: boolean;

    // @ts-ignore
    private tableCards: BgaCards.SlotStock<Card>[] = [];

    constructor(private game: Game, player: PixiesPlayer) {
        this.playerId = Number(player.id);
        this.currentPlayer = this.playerId == this.game.getPlayerId();

        let html = `
        <div id="player-table-${this.playerId}" class="player-table" style="border-color: #${player.color}; --grid-size: ${this.game.gamedatas.littleGiantsExpansion ? 4 : 3};">
            <div class="name-wrapper">
                <span class="name" style="color: #${player.color};" data-color="${player.color}">${player.name}</span>
            </div>
            <div id="player-table-${this.playerId}-cards" class="player-cards">`;
        const min = this.game.gamedatas.littleGiantsExpansion ? 0 : 1;
        for (let row = min; row <= 3; row++) {
            for (let column = min; column <= 3; column++) {
                let value = `${(row-1)*3 + column}`;
                if (row === 0 && column === 0) {
                    value = '';
                } else if (row === 0) {
                    value = '🠷';
                } else if (column === 0) {
                    value = '🠶';
                }   
                html += `
                    <div id="player-table-${this.playerId}-cards-${row}-${column}" class="${row === 0 && column === 0 ? '' : 'space'}" style="--value: '${value}';"></div>`;
            }
        }
        html += `
            </div>
        </div>`;
        document.getElementById('tables').insertAdjacentHTML('beforeend', html);

        // @ts-ignore
        const stockSettings: BgaCards.SlotStockSettings<Card> = {
            slotsIds: [0, 1],
            mapCardToSlot: card => card.locationArg,
        }

        for (let row = min; row <= 3; row++) {
            for (let column = min; column <= 3; column++) {
                if (row === 0 && column === 0) {
                    continue;
                }
                const spaceDiv = document.getElementById(`player-table-${this.playerId}-cards-${row}-${column}`);
                spaceDiv.addEventListener('click', () => {
                    if (spaceDiv.classList.contains('selectable')) {
                        this.game.onSpaceClick(row, column);
                    }
                })
                this.tableCards[`${row}-${column}`] = new BgaCards.SlotStock/*<Card>*/(this.game.cardsManager, spaceDiv, stockSettings);
                this.tableCards[`${row}-${column}`].addCards(player.cards.filter(card => card.row === row && card.column === column));
            }
        }
    }
    
    public getAllCards(): Card[] {
        const cards = [];

        const min = this.game.gamedatas.littleGiantsExpansion ? 0 : 1;
        for (let row = min; row <= 3; row++) {
            for (let column = min; column <= 3; column++) {
                if (row === 0 && column === 0) {
                    continue;
                }
                cards.push(...this.tableCards[`${row}-${column}`].getCards());
            }
        }

        return cards;
    }
    
    public async playCard(card: Card, row: number, column: number) {
        await this.tableCards[`${row}-${column}`].addCard(card);
    }
    
    public async keepCard(hiddenCard: Card, visibleCard: Card, row: number, column: number) {
        this.game.cardsManager.updateCardInformations(hiddenCard);
        await Promise.all([
            this.tableCards[`${row}-${column}`].addCard(hiddenCard),
            this.game.animationManager.animationsActive() ? this.game.bga.gameui.wait(ANIMATION_MS) : Promise.resolve(true),
        ]);
        this.game.cardsManager.updateCardInformations(visibleCard);
        await Promise.all([
            this.tableCards[`${row}-${column}`].addCard(visibleCard),
            this.game.animationManager.animationsActive() ? this.game.bga.gameui.wait(ANIMATION_MS) : Promise.resolve(true),
        ]);
    }
    
    public setSelectableSpaces(spaces: string[]) {
        const min = this.game.gamedatas.littleGiantsExpansion ? 0 : 1;
        for (let row = min; row <= 3; row++) {
            for (let column = min; column <= 3; column++) {
                if (row === 0 && column === 0) {
                    continue;
                }
                document.getElementById(`player-table-${this.playerId}-cards-${row}-${column}`).classList.toggle('selectable', spaces.includes(`${row}-${column}`));
            }
        }
    }
}