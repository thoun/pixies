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

    public setupFrontDiv(card: Card, div: HTMLElement, ignoreTooltip: boolean = false) {
        div.dataset.color = ''+card.color;
        div.dataset.index = ''+card.index;
        if (!ignoreTooltip) {
            this.game.setTooltip(div.id, this.getTooltip(card) + `<br><i>${this.COLORS[card.color]}</i>`);
        }
    }

    public getTooltip(card: Card) {
        return `
        <div><strong>${_("Spirals:")}</strong> ${card.spirals == -1 ? _("1 per ${color}".replace('${color}', this.COLORS[card.color])) : card.spirals}</div>
        <div><strong>${_("Crosses:")}</strong> ${card.crosses}</div>`;
    }
    
    public setForHelp(card: Card, divId: string): void {
        const div = document.getElementById(divId);
        div.classList.add('card');
        div.dataset.side = 'front';
        div.innerHTML = `
        <div class="card-sides">
            <div class="card-side front">
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