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

class CardsManager extends CardManager<Card> {
    private COLORS: string[];
    
    constructor (public game: PixiesGame) {
        super(game, {
            getId: (card) => `card-${card.id}`,
            setupDiv: (card: Card, div: HTMLElement) => {
                div.dataset.cardId = ''+card.id;
            },
            setupFrontDiv: (card: Card, div: HTMLElement) => this.setupFrontDiv(card, div),
            isCardVisible: card => Boolean(card.index),
            animationManager: game.animationManager,
            cardWidth: 149,
            cardHeight: 208,
        });

        this.COLORS = [
            _('Multicolor'),
            _('Blue'),
            _('Green'),
            _('Yellow'),
            _('Red'),
        ];
    }

    private getFlowerPowerIndex(card: Card): number | null {
        let flowerPowerIndex = null;
        if (card.type > 10) {
            switch (card.type) {
                case 12: // Blue and Green
                    switch (card.index) {
                        case 1: flowerPowerIndex = 4; break;
                        case 2: flowerPowerIndex = 7; break;
                    }
                    break;
                case 13: // Blue and Yellow
                    switch (card.index) {
                        case 1: flowerPowerIndex = 3; break;
                        case 2: flowerPowerIndex = 6; break;
                        case 3: flowerPowerIndex = 12; break;
                    }
                    break;
                case 14: // Blue and Red
                    switch (card.index) {
                        case 1: flowerPowerIndex = 1; break;
                        case 2: flowerPowerIndex = 11; break;
                    }
                    break;
                case 23: // Green and Yellow
                    switch (card.index) {
                        case 1: flowerPowerIndex = 0; break;
                        case 2: flowerPowerIndex = 13; break;
                    }
                    break;
                case 24: // Green and Red
                    switch (card.index) {
                        case 1: flowerPowerIndex = 2; break;
                        case 2: flowerPowerIndex = 8; break;
                        case 3: flowerPowerIndex = 10; break;
                    }
                    break;
                case 34: // Yellow and Red
                    switch (card.index) {
                        case 1: flowerPowerIndex = 5; break;
                        case 2: flowerPowerIndex = 9; break;
                    }
                    break;
            }
        }
        return flowerPowerIndex;
    }

    public setupFrontDiv(card: Card, div: HTMLElement, ignoreTooltip: boolean = false) {
        div.dataset.type = ''+card.type;
        div.dataset.index = ''+card.index;
        if (!ignoreTooltip && card.type) {
            let flowerPowerIndex = this.getFlowerPowerIndex(card);
            if (flowerPowerIndex !== null) {
                div.classList.add('flower-power');
                div.style.backgroundPositionX = `${flowerPowerIndex * 100 / 13}%`;
            }

            let tooltip = this.getTooltip(card) + `<br><i>${card.type === 0 ? this.COLORS[0] : card.colors.map(color => this.COLORS[color]).join(' / ')}</i><br>
            <div class="card double-size">
                <div class="card-sides">
                    <div class="card-side front ${flowerPowerIndex !== null ? 'flower-power': ''}" data-type="${card.type}" data-index="${card.index}" ${flowerPowerIndex !== null ? `style="background-position-x: ${flowerPowerIndex * 100 / 13}%`: '' }">
                    </div>
                </div>
            </div>`;
            this.game.setTooltip(div.id, tooltip);
        }
    }

    public getTooltip(card: Card) {
        return `
        <div><strong>${_("Spirals:")}</strong> ${card.spirals == -1 ? _("1 per ${color}".replace('${color}', this.COLORS[card.colors[0]])) : card.spirals}</div>
        <div><strong>${_("Crosses:")}</strong> ${card.crosses < 0 ? _("1 per ${color}".replace('${color}', this.COLORS[-card.crosses])) : card.crosses}</div>
        `;
    }
    
    public setForHelp(card: Card, divId: string): void {
        const div = document.getElementById(divId);
        div.classList.add('card');
        div.dataset.side = 'front';

        let flowerPowerIndex = this.getFlowerPowerIndex(card);
        if (flowerPowerIndex !== null) {
            div.classList.add('flower-power');
            div.style.backgroundPositionX = `${flowerPowerIndex * 100 / 13}%`;
        }

        div.innerHTML = `
        <div class="card-sides">
            <div class="card-side front ${flowerPowerIndex !== null ? 'flower-power': ''}" ${flowerPowerIndex !== null ? `style="background-position-x: ${flowerPowerIndex * 100 / 13}%`: '' }">
            </div>
            <div class="card-side back">
            </div>
        </div>`
        this.setupFrontDiv(card, div.querySelector('.front'), true);
    }

    // gameui.cards.debugSeeAllCards()
    private debugSeeAllCards() {
        let html = `<div id="all-cards">`;
        html += `</div>`;
        dojo.place(html, 'full-table', 'before');

        const debugStock = new LineStock<Card>(this.game.cardsManager, document.getElementById(`all-cards`), { gap: '0', });

        [1, 2, 3, 4, 5, 6].forEach(subType => {
            const card = {
                id: 10+subType,
                type: 1,
                subType,
            } as any as Card;
            debugStock.addCard(card);
        });

        [2, 3, 4, 5, 6].forEach(type => 
            [1, 2, 3].forEach(subType => {
                const card = {
                    id: 10*type+subType,
                    type,
                    subType,
                } as any as Card;
                debugStock.addCard(card);
            })
        );
    }

    public getColor(color: number): string {
        switch (color) {
            case 0: return _("Multicolor");
            case 1: return _("Blue");
            case 2: return _("Green");
            case 3: return _("Yellow");
            case 4: return _("Red");
        }
    }
}