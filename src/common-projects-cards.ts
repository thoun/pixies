class CommonProjectCards {
    constructor(private game: GardenNationGame) {}  

    // gameui.commonProjectCards.debugSeeAllCards()
    private debugSeeAllCards() {
        document.querySelectorAll('.card.common-project').forEach(card => card.remove());
        
        let html = `<div id="all-common-project-cards">`;
        html += `</div>`;
        dojo.place(html, 'full-table', 'before');

        [1, 2, 3, 4, 5, 6].forEach(subType => {
            const card = {
                id: 10+subType,
                type: 1,
                subType,
                name: this.getTitle(1, subType)
            } as any as CommonProject;
            this.createMoveOrUpdateCard(card, `all-common-project-cards`);
        });

        [2, 3, 4, 5, 6].forEach(type => 
            [1, 2, 3].forEach(subType => {
                const card = {
                    id: 10*type+subType,
                    type,
                    subType,
                    name: this.getTitle(type, subType)
                } as any as CommonProject;
                this.createMoveOrUpdateCard(card, `all-common-project-cards`);
            })
        );
    }

    public createMoveOrUpdateCard(card: CommonProject, destinationId: string, init: boolean = false, from: string = null) {
        const existingDiv = document.getElementById(`common-project-${card.id}`);
        const side = (card.type ? 0 : 1)
        if (existingDiv) {
            (this.game as any).removeTooltip(`common-project-${card.id}`);
            const oldType = Number(existingDiv.dataset.type);

            if (init) {
                document.getElementById(destinationId).appendChild(existingDiv);
            } else {
                slideToObjectAndAttach(this.game, existingDiv, destinationId);
            }
            existingDiv.dataset.side = ''+side;
            if (!oldType && card.type) {
                this.setVisibleInformations(existingDiv, card);
            }
            this.game.setTooltip(existingDiv.id, this.getTooltip(card.type, card.subType));
        } else {
            const div = document.createElement('div');
            div.id = `common-project-${card.id}`;
            div.classList.add('card', 'common-project');
            div.dataset.side = ''+side;
            div.dataset.type = ''+card.type;
            div.dataset.subType = ''+card.subType;

            div.innerHTML = `
                <div class="card-sides">
                    <div class="card-side front">
                        <div id="${div.id}-name" class="name">${card.type ? this.getTitle(card.type, card.subType) : ''}</div>
                    </div>
                    <div class="card-side back">
                    </div>
                </div>
            `;
            document.getElementById(destinationId).appendChild(div);
            div.addEventListener('click', () => this.game.onCommonProjectClick(card));

            if (from) {
                const fromCardId = document.getElementById(from).children[0].id;
                slideFromObject(this.game, div, fromCardId);
            }

            if (card.type) {
                this.setVisibleInformations(div, card);
            }
            this.game.setTooltip(div.id, this.getTooltip(card.type, card.subType));
        }
    }

    private setVisibleInformations(div: HTMLElement, card: CommonProject) {
        if (card.name) {
            document.getElementById(`${div.id}-name`).innerHTML = _(card.name);
        }
        div.dataset.type = ''+card.type;
        div.dataset.subType = ''+card.subType;
    }

    getTitle(type: number, subType: number) {
        switch(type) {
            case 1:
                switch(subType) {
                    case 1: case 2: return _('Infirmary');
                    case 3: case 4: return _('Sacred Place');
                    case 5: case 6: return _('Fortress');
                }
            case 2:
                switch(subType) {
                    case 1: return _('Herbalist');
                    case 2: return _('House');
                    case 3: return _('Prison');
                }
            case 3:
                switch(subType) {
                    case 1: return _('Forge');
                    case 2: return _('Terraced Houses');
                    case 3: return _('Outpost');
                }
            case 4:
                switch(subType) {
                    case 1: return _('Windmill');
                    case 2: return _('Sanctuary');
                    case 3: return _('Bunker');
                }
            case 5:
                switch(subType) {
                    case 1: return _('Power Station');
                    case 2: return _('Apartments');
                    case 3: return _('Radio Tower');
                }
            case 6:
                switch(subType) {
                    case 1: return _('Water Reservoir');
                    case 2: return _('Temple');
                    case 3: return _('Air Base');
                }
        }
            
    }

    getTooltip(type: number, subType: number) {
        if (!type) {
            return _('Common projects deck');
        }
        return `<h3 class="title">${this.getTitle(type, subType)}</h3><div>${this.getTooltipDescription(type)}</div>`;
    }

    getTooltipDescription(type: number) {
        switch(type) {
            case 1: return _('Construct a building with at least 2 floors on an area adjacent to an unoccupied area, respecting the indicated land types (1 copy each).');
            case 2: return _('Construct a building with at least 2 floors on the indicated land type in one of the 6 outside territories (1 copy each).');
            case 3: return _('Construct 2 buildings with at least 1 floor on 2 adjacent areas of the indicated land type (1 copy each).');
            case 4: return _('Construct 2 buildings, 1 with at least 2 floors and 1 with at least 1 floor, on 2 adjacent areas, respecting the indicated land type (1 copy each).');
            case 5: return _('Construct a building with at least 3 floors on the indicated land type in the central territory (1 copy each).');
            case 6: return _('Construct 3 buildings, 1 with at least 2 floors adjacent to 2 buildings with at least 1 floor respecting the indicated land types (1 copy each).');
        }
    }
}