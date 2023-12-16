class CardsManager extends CardManager<Card> {
    private COLORS: string[];
    
    constructor (public game: PixiesGame) {
        super(game, {
            getId: (card) => `ssp-card-${card.id}`,
            setupDiv: (card: Card, div: HTMLElement) => {
                div.dataset.cardId = ''+card.id;
            },
            setupFrontDiv: (card: Card, div: HTMLElement) => this.setupFrontDiv(card, div),
            isCardVisible: card => Boolean(card.category),
            animationManager: game.animationManager,
            cardWidth: 149,
            cardHeight: 208,
        });

        this.COLORS = [
            _('White'),
            _('Dark blue'),
            _('Light blue'),
            _('Black'),
            _('Yellow'),
            _('Green'),
            _('Purple'),
            _('Gray'),
            _('Light orange'),
            _('Pink'),
            _('Orange'),
        ];
    }

    public setupFrontDiv(card: Card, div: HTMLElement, ignoreTooltip: boolean = false) {
        div.dataset.category = ''+card.category;
        div.dataset.family = ''+card.family;
        div.dataset.color = ''+card.color;
        div.dataset.index = ''+card.index;
        if (!ignoreTooltip) {
            this.game.setTooltip(div.id, this.getTooltip(card.category, card.family) + `<br><br><i>${this.COLORS[card.color]}</i>`);
        }
    }

    public getTooltip(category: number, family?: number/*, withCount: boolean = false*/) {
        const withCount = true;
        switch(category) {
            case 1:
                return `
                <div><strong>${_("Mermaid")}</strong> ${withCount ? '(x4)' : ''}</div>
                ${_("1 point for each card of the color the player has the most of. If they have more mermaid cards, they must look at which of the other colors they have more of. The same color cannot be counted for more than one mermaid card.")}
                <br><br>
                <strong>${_("Effect: If they place 4 mermaid cards, the player immediately wins the game.")}</strong>`;
            case 2:
                const swimmerSharkEffect = _("The player steals a random card from another player and adds it to their hand.");
                const swimmerJellyfishEffect = _("On their next turn, opposing players can only draw the first card from the deck. They cannot play any cards nor end the round.");
                const crabLobsterEffect = _("The player takes the first five cards from the deck, adds one of them to their hand, then returns the other four to the deck and shuffles it.");
                
                const duoCards = [
                    [_('Crab'), [
                        [_('Crab'), _("The player chooses a discard pile, consults it without shuffling it, and chooses a card from it to add to their hand. They do not have to show it to the other players.")],
                        
                    ], 9],
                    [_('Boat'), [
                        [_('Boat'), _("The player immediately takes another turn.")]
                    ], 8],
                    [_('Fish'), [
                        [_('Fish'), _("The player adds the top card from the deck to their hand.")]
                    ], 7],
                    [_('Swimmer'), [
                        [_('Shark'), swimmerSharkEffect]
                    ], 5],
                    [_('Shark'), [
                        [_('Swimmer'), swimmerSharkEffect]
                    ], 5],
                ];

                if (this.game.isExpansion()) {
                    duoCards[0][1].push(
                        [_('Lobster'), crabLobsterEffect],
                    );
                    duoCards[3][1].push(
                        [_('Jellyfish'), swimmerJellyfishEffect],
                    );
                    duoCards.push([_('Jellyfish'), [
                        [_('Swimmer'), swimmerJellyfishEffect]
                    ], 2], 
                    [_('Lobster'), [
                        [_('Crab'), crabLobsterEffect]
                    ], 1], )
                }

                const duo = duoCards[family - 1];
                let html = `<div><strong>${duo[0]}</strong> ${withCount ? `(x${duo[2]})` : ''}</div>
                <div>${_("1 point for each valid pair of cards.")}</div><br>
                <div>${_("Effect:")}</div><div>`;
                duo[1].forEach(possiblePair => {
                    html += `<div><i>${(possiblePair[0] == duo[0] ? _("With another ${card_type}:") : _("With a ${card_type}:")).replace('${card_type}', possiblePair[0])}</i> ${possiblePair[1]}</div>`;
                });
                html += `</div>`;
                return html;
            case 3:
                const collectorCards = [
                    ['0, 2, 4, 6, 8, 10', '1, 2, 3, 4, 5, 6', _('Shell')],
                    ['0, 3, 6, 9, 12', '1, 2, 3, 4, 5', _('Octopus')],
                    ['1, 3, 5', '1, 2, 3', _('Penguin')],
                    ['0, 5', '1,  2', _('Sailor')],
                ];
                const collector = collectorCards[family - 1];
                return `<div><strong>${collector[2]}</strong> ${withCount ? `(x${collector[0].split(',').length})` : ''}</div>
                <div>${_("${points} points depending on whether the player has ${numbers} ${card} cards.").replace('${points}', collector[0]).replace('${numbers}', collector[1]).replace('${card}', collector[2])}</div>`;
            case 4:
                const multiplierCards = [
                    [_('The lighthouse'), _('Boat'), 1],
                    [_('The shoal of fish'), _('Fish'), 1],
                    [_('The penguin colony'), _('Penguin'), 2],
                    [_('The captain'), _('Sailor'), 3],
                    [_('The cast of crabs'), _('Crab'), 1],
                ];
                const multiplier = multiplierCards[family - 1];
                return `<div><strong>${multiplier[0]}</strong> (x1)</div>
                <div>${_("${points} point(s) per ${card} card.").replace('${points}', multiplier[2]).replace('${card}', multiplier[1])}</div>
                <div>${_("This card does not count as a ${card} card.").replace('${card}', multiplier[1])}</div>`;
            case 5:
            const specialCards = [
                [_('Starfish'), 3, _("If a player has a duo and a starfish card in their hand, they can form a trio and place these three cards in front of them. The starfish adds 2 points to the duo (so the trio is worth 3 points). Cancels the effect of the duo cards placed with the starfish.")],
                [_('Seahorse'), 1, _("The player can use the seahorse to replace a missing Collector card (octopus, shell, penguin or sailor). They must have at least one card for that collection in their hand. They cannot gain more points than the maximum indicated on the matching Collector card.")],
            ];
            const special = specialCards[family - 1];
            return `<div><strong>${special[0]}</strong> (x${special[1]})</div>
            <div>${special[2]}</div>`;
        }
            
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
}