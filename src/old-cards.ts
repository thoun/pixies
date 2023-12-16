class Cards {
    private COLORS: string[] = [];

    constructor(private game: PixiesGame) {
    }  

    public createMoveOrUpdateCard(card: Card, destinationId: string, instant: boolean = false, from: string = null) {
        const existingDiv = document.getElementById(`card-${card.id}`);
        const side = card.category ? 'front' : 'back';
        if (existingDiv) {
            (this.game as any).removeTooltip(`card-${card.id}`);
            const oldType = Number(existingDiv.dataset.category);
            existingDiv.classList.remove('selectable', 'selected', 'disabled');

            if (existingDiv.parentElement.id != destinationId) {
                if (instant) {
                    document.getElementById(destinationId).appendChild(existingDiv);
                } else {
                    slideToObjectAndAttach(this.game, existingDiv, destinationId);
                }
            }

            existingDiv.dataset.side = ''+side;
            if (!oldType && card.category) {
                this.setVisibleInformations(existingDiv, card);
            } else if (oldType && !card.category) {
                if (instant) {
                    this.removeVisibleInformations(existingDiv);
                } else {
                    setTimeout(() => this.removeVisibleInformations(existingDiv), 500); // so we don't change face while it is still visible
                }
            }
            if (card.category) {
                this.game.setTooltip(existingDiv.id, this.game.cardsManager.getTooltip(card.category, card.family) + `<br><br><i>${this.COLORS[card.color]}</i>`);
            }
        } else {
            const div = document.createElement('div');
            div.id = `card-${card.id}`;
            div.classList.add('old-card');
            div.dataset.id = ''+card.id;
            div.dataset.side = ''+side;

            div.innerHTML = `
                <div class="old-card-sides">
                    <div class="old-card-side front">
                    </div>
                    <div class="old-card-side back">
                    </div>
                </div>
            `;
            document.getElementById(destinationId).appendChild(div);
            div.addEventListener('click', () => this.game.onCardClick(card));

            if (from) {
                const fromCardId = document.getElementById(from).id;
                slideFromObject(this.game, div, fromCardId);
            }

            if (card.category) {
                this.setVisibleInformations(div, card);
                if (!destinationId.startsWith('help-')) {
                    this.game.setTooltip(div.id, this.game.cardsManager.getTooltip(card.category, card.family) + `<br><br><i>${this.COLORS[card.color]}</i>`);
                }
            }
        }
    }

    public updateCard(card: Card) {
        const existingDiv = document.getElementById(`card-${card.id}`);
        const side = card.category ? 'front' : 'back';
        if (existingDiv) {
            (this.game as any).removeTooltip(`card-${card.id}`);
            const oldType = Number(existingDiv.dataset.category);
            existingDiv.dataset.side = ''+side;
            if (!oldType && card.category) {
                this.setVisibleInformations(existingDiv, card);
            } else if (oldType && !card.category) {
                setTimeout(() => this.removeVisibleInformations(existingDiv), 500); // so we don't change face while it is still visible
            }
            if (card.category) {
                this.game.setTooltip(existingDiv.id, this.game.cardsManager.getTooltip(card.category, card.family) + `<br><br><i>${this.COLORS[card.color]}</i>`);
            }
        }
    }

    private setVisibleInformations(div: HTMLElement, card: Card) {
        div.dataset.category = ''+card.category;
        div.dataset.family = ''+card.family;
        div.dataset.color = ''+card.color;
        div.dataset.index = ''+card.index;
    }

    private removeVisibleInformations(div: HTMLElement) {
        div.removeAttribute('data-category');
        div.removeAttribute('data-family');
        div.removeAttribute('data-color');
        div.removeAttribute('data-index');
    }

    public removeCard(div: HTMLElement) {
        if (!div) {
            return;
        }

        div.id = `deleted${div.id}`;
        this.removeVisibleInformations(div);
        div.remove();
    }
}