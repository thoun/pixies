const BgaZoom = await globalThis.importEsmLib('bga-zoom', '1.x');
const [BgaHelp, BgaAnimations, BgaCards] = await globalThis.importDojoLibs([
    g_gamethemeurl + "modules/js/bga-help.js",
    g_gamethemeurl + "modules/js/bga-animations.js",
    g_gamethemeurl + "modules/js/bga-cards.js",
]);

class CardsManager extends BgaCards.CardManager {
    constructor(game) {
        super(game, {
            getId: (card) => `card-${card.id}`,
            setupDiv: (card, div) => {
                div.dataset.cardId = '' + card.id;
            },
            setupFrontDiv: (card, div) => this.setupFrontDiv(card, div),
            isCardVisible: card => Boolean(card.index) && !card.flipped,
            animationManager: game.animationManager,
            cardWidth: 149,
            cardHeight: 208,
        });
        this.game = game;
        this.COLORS = [
            _('Multicolor'),
            _('Blue'),
            _('Green'),
            _('Yellow'),
            _('Red'),
        ];
    }
    getFlowerPowerIndex(card) {
        let flowerPowerIndex = null;
        if (card.type > 10) {
            switch (card.type) {
                case 12: // Blue and Green
                    switch (card.index) {
                        case 1:
                            flowerPowerIndex = 4;
                            break;
                        case 2:
                            flowerPowerIndex = 7;
                            break;
                    }
                    break;
                case 13: // Blue and Yellow
                    switch (card.index) {
                        case 1:
                            flowerPowerIndex = 3;
                            break;
                        case 2:
                            flowerPowerIndex = 6;
                            break;
                        case 3:
                            flowerPowerIndex = 12;
                            break;
                    }
                    break;
                case 14: // Blue and Red
                    switch (card.index) {
                        case 1:
                            flowerPowerIndex = 1;
                            break;
                        case 2:
                            flowerPowerIndex = 11;
                            break;
                    }
                    break;
                case 23: // Green and Yellow
                    switch (card.index) {
                        case 1:
                            flowerPowerIndex = 0;
                            break;
                        case 2:
                            flowerPowerIndex = 13;
                            break;
                    }
                    break;
                case 24: // Green and Red
                    switch (card.index) {
                        case 1:
                            flowerPowerIndex = 2;
                            break;
                        case 2:
                            flowerPowerIndex = 8;
                            break;
                        case 3:
                            flowerPowerIndex = 10;
                            break;
                    }
                    break;
                case 34: // Yellow and Red
                    switch (card.index) {
                        case 1:
                            flowerPowerIndex = 5;
                            break;
                        case 2:
                            flowerPowerIndex = 9;
                            break;
                    }
                    break;
            }
        }
        return flowerPowerIndex;
    }
    setupFrontDiv(card, div, ignoreTooltip = false) {
        div.dataset.type = '' + card.type;
        div.dataset.index = '' + card.index;
        if (!ignoreTooltip && card.type !== null && card.type !== undefined) {
            let flowerPowerIndex = this.getFlowerPowerIndex(card);
            if (flowerPowerIndex !== null) {
                div.classList.add('flower-power');
                div.style.backgroundPositionX = `${flowerPowerIndex * 100 / 13}%`;
            }
            else if (card.index > 100) {
                div.classList.add('little-giants');
                div.style.backgroundPositionX = `${(card.index - 101) * 100 / 13}%`;
            }
            let tooltip = this.getTooltip(card) + `<br><i>${card.type === 0 ? this.COLORS[0] : card.colors.map(color => this.COLORS[color]).join(' / ')}</i><br>
            <div class="card double-size">
                <div class="card-sides">
                    <div class="card-side front ${flowerPowerIndex !== null ? 'flower-power' : ''}${card.index > 100 ? 'little-giants' : ''}" data-type="${card.type}" data-index="${card.index}" ${flowerPowerIndex !== null ? `style="background-position-x: ${flowerPowerIndex * 100 / 13}%` : ''}${card.index > 100 ? `style="background-position-x: ${(card.index - 101) * 100 / 13}%` : ''}">
                    </div>
                </div>
            </div>`;
            this.game.setTooltip(div.id, tooltip);
        }
    }
    getEffect(effect) {
        switch (effect) {
            case 1: return `<div>${_('This card earns you 3 spirals for each <strong>validated</strong> card in its column.')}</div>`;
            case 2: return `<div>${_('This card earns you 4 spirals for each faceup card in its column <strong>that has no spirals</strong>.')}</div>`;
            case 3: return `<div>${_('This card earns you 2 spirals for each faceup card in its row that is the <strong>indicated color</strong>. It earns 2 spirals for itself, since it is of the indicated color.')}</div>`;
            case 4: return `<div>${_('This card <strong>cancels all crosses</strong> on faceup cards in its row (regardless of where those crosses come from).')}</div>`;
            case 5: return `<div>${_('This card earns you 2 or 3 spirals (as shown) for each faceup card in its row or column that is <strong>not validated</strong>.')}</div>`;
        }
    }
    getTooltip(card) {
        const fullEffect = card.rowEffect || card.columnEffect;
        if (fullEffect) {
            return this.getEffect(Math.floor(fullEffect / 10));
        }
        return `
        <div><strong>${_("Spirals:")}</strong> ${card.spirals == -1 ? _("1 per ${color}".replace('${color}', this.COLORS[card.colors[0]])) : card.spirals}</div>
        <div><strong>${_("Crosses:")}</strong> ${card.crosses < 0 ? _("1 per ${color}".replace('${color}', this.COLORS[-card.crosses])) : card.crosses}</div>
        `;
    }
    setForHelp(card, divId) {
        const div = document.getElementById(divId);
        div.classList.add('card');
        div.dataset.side = 'front';
        let flowerPowerIndex = this.getFlowerPowerIndex(card);
        if (flowerPowerIndex !== null) {
            div.classList.add('flower-power');
            div.style.backgroundPositionX = `${flowerPowerIndex * 100 / 13}%`;
        }
        else if (card.index > 100) {
            div.classList.add('little-giants');
            div.style.backgroundPositionX = `${(card.index - 101) * 100 / 13}%`;
        }
        div.innerHTML = `
        <div class="card-sides">
            <div class="card-side front ${flowerPowerIndex !== null ? 'flower-power' : ''}" ${flowerPowerIndex !== null ? `style="background-position-x: ${flowerPowerIndex * 100 / 13}%` : ''}">
            </div>
            <div class="card-side back">
            </div>
        </div>`;
        this.setupFrontDiv(card, div.querySelector('.front'), true);
    }
    // gameui.cards.debugSeeAllCards()
    /*private debugSeeAllCards() {
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
    }*/
    getColor(color) {
        switch (color) {
            case 0: return _("Multicolor");
            case 1: return _("Blue");
            case 2: return _("Green");
            case 3: return _("Yellow");
            case 4: return _("Red");
        }
    }
}

const ANIMATION_MS$1 = 500;
class PlayerTable {
    constructor(game, player) {
        this.game = game;
        // @ts-ignore
        this.tableCards = [];
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
                let value = `${(row - 1) * 3 + column}`;
                if (row === 0 && column === 0) {
                    value = '';
                }
                else if (row === 0) {
                    value = '🠷';
                }
                else if (column === 0) {
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
        const stockSettings = {
            slotsIds: [0, 1],
            mapCardToSlot: card => card.locationArg,
        };
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
                });
                this.tableCards[`${row}-${column}`] = new BgaCards.SlotStock /*<Card>*/(this.game.cardsManager, spaceDiv, stockSettings);
                this.tableCards[`${row}-${column}`].addCards(player.cards.filter(card => card.row === row && card.column === column));
            }
        }
    }
    getAllCards() {
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
    async playCard(card, row, column) {
        await this.tableCards[`${row}-${column}`].addCard(card);
    }
    async keepCard(hiddenCard, visibleCard, row, column) {
        this.game.cardsManager.updateCardInformations(hiddenCard);
        await Promise.all([
            this.tableCards[`${row}-${column}`].addCard(hiddenCard),
            this.game.animationManager.animationsActive() ? this.game.bga.gameui.wait(ANIMATION_MS$1) : Promise.resolve(true),
        ]);
        this.game.cardsManager.updateCardInformations(visibleCard);
        await Promise.all([
            this.tableCards[`${row}-${column}`].addCard(visibleCard),
            this.game.animationManager.animationsActive() ? this.game.bga.gameui.wait(ANIMATION_MS$1) : Promise.resolve(true),
        ]);
    }
    setSelectableSpaces(spaces) {
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

class TableCenter {
    constructor(game, gamedatas) {
        this.game = game;
        const tableCardsDiv = document.getElementById(`table-cards`);
        this.tableCards = new BgaCards.LineStock /*<Card>*/(this.game.cardsManager, tableCardsDiv);
        this.tableCards.onCardClick = card => this.game.onTableCardClick(card);
        this.tableCards.addCards(gamedatas.tableCards);
        this.deck = new BgaCards.Deck /*<Card>*/(this.game.cardsManager, document.getElementById('deck'), {
            cardNumber: gamedatas.remainingCardsInDeck,
            /*counter: {
                extraClasses: 'pile-counter',
            }*/
        });
    }
    getTableCards() {
        return this.tableCards.getCards();
    }
    newTurn(cards) {
        return this.tableCards.addCards(cards, {
            fromStock: this.deck,
        }, undefined, 250);
    }
    makeCardsSelectable(selectable) {
        this.tableCards.setSelectionMode(selectable ? 'single' : 'none');
    }
    setSelectedCard(selectedCard) {
        this.game.cardsManager.getCardElement(selectedCard)?.classList.add('bga-cards_selected-card');
    }
}

const ANIMATION_MS = 500;
const LOCAL_STORAGE_ZOOM_KEY = 'Pixies-zoom';
class Game {
    constructor(bga) {
        this.playersTables = [];
        this.TOOLTIP_DELAY = document.body.classList.contains('touch-device') ? 1500 : undefined;
        this.bga = bga;
    }
    /*
        setup:

        This method must set up the game user interface according to current game situation specified
        in parameters.

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)

        "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
    */
    setup(gamedatas) {
        console.log("Starting game setup");
        this.gamedatas = gamedatas;
        if (gamedatas.flowerPowerExpansion) {
            this.bga.images.dontPreloadImage('background.jpg');
            document.getElementsByTagName('html')[0].classList.add('flower-power-expansion');
        }
        else {
            this.bga.images.dontPreloadImages(['background-expansion.jpg', 'flower-power-cards.jpg']);
        }
        if (!gamedatas.littleGiantsExpansion) {
            this.bga.images.dontPreloadImages(['little-giants-cards.webp']);
        }
        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
            <div id="result"></div>

            <div id="full-table">
                <div id="centered-table">
                    <div id="table-center">
                        <div id="round-counter-wrapper" class="whiteblock">
                            <div>${_("Round")}</div>
                            <div class="counter"><span id="round-counter"></span><span>&nbsp;/&nbsp;3</span></div>
                        </div>
                        <div id="deck" class="cards-stack"></div>
                        <div id="table-cards"></div>
                    </div>
                    <div id="tables"></div>
                </div>
            </div>
        `);
        this.gamedatas = gamedatas;
        console.log('gamedatas', gamedatas);
        this.animationManager = new BgaAnimations.AnimationManager(this);
        this.cardsManager = new CardsManager(this);
        this.tableCenter = new TableCenter(this, this.gamedatas);
        this.createPlayerTables(gamedatas);
        this.zoomManager = new BgaZoom.Manager({
            element: document.getElementById('full-table'),
            zoomControls: {
                color: 'white',
            },
            zoomLevels: [0.25, 0.375, 0.5, 0.625, 0.75, 0.875, 1, 1.25, 1.5, 1.75, 2],
            localStorageZoomKey: LOCAL_STORAGE_ZOOM_KEY,
        });
        this.roundCounter = new ebg.counter();
        this.roundCounter.create('round-counter');
        this.roundCounter.setValue(gamedatas.roundNumber);
        if (gamedatas.lastTurn) {
            this.notif_lastTurn(false);
        }
        this.setupNotifications();
        new BgaHelp.HelpManager(this, {
            buttons: [
                new BgaHelp.BgaHelpPopinButton({
                    title: _("Scoring"),
                    html: this.getHelpHtml(),
                    buttonBackground: '#3d5c28',
                }),
                new BgaHelp.BgaHelpExpandableButton({
                    unfoldedHtml: this.getColorAddHtml(),
                    foldedContentExtraClasses: 'color-help-folded-content',
                    unfoldedContentExtraClasses: 'color-help-unfolded-content',
                    expandedWidth: '120px',
                    expandedHeight: '168px',
                }),
            ]
        });
        if (gamedatas.roundResult[gamedatas.roundNumber]) {
            this.setRoundResult(gamedatas.roundResult[gamedatas.roundNumber], gamedatas.roundNumber);
        }
        console.log("Ending game setup");
    }
    ///////////////////////////////////////////////////
    //// Game & client states
    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    onEnteringState(stateName, args) {
        console.log('Entering state: ' + stateName, args.args);
        switch (stateName) {
            case 'chooseCard':
                this.onEnteringChooseCard(args.args);
                break;
            case 'playCard':
                this.onEnteringPlayCard(args.args);
                break;
            case 'keepCard':
                this.onEnteringKeepCard(args.args);
                break;
        }
    }
    onEnteringChooseCard(args) {
        if (this.bga.players.isCurrentPlayerActive()) {
            this.tableCenter.makeCardsSelectable(true);
        }
    }
    onEnteringPlayCard(args) {
        this.tableCenter.setSelectedCard(args.selectedCard);
        if (this.bga.players.isCurrentPlayerActive()) {
            this.getCurrentPlayerTable()?.setSelectableSpaces(args.spaces);
        }
    }
    onEnteringKeepCard(args) {
        this.tableCenter.setSelectedCard(args.selectedCard);
    }
    onLeavingState(stateName) {
        console.log('Leaving state: ' + stateName);
        switch (stateName) {
            case 'chooseCard':
                this.onLeavingChooseCard();
                break;
            case 'playCard':
                this.onLeavingPlayCard();
                break;
            case 'keepCard':
                this.onLeavingKeepCard();
                break;
        }
    }
    onLeavingChooseCard() {
        this.tableCenter.makeCardsSelectable(false);
    }
    onLeavingPlayCard() {
        //this.tableCenter.removeSelectedCard();  
        this.getCurrentPlayerTable()?.setSelectableSpaces([]);
    }
    onLeavingKeepCard() {
        //this.tableCenter.removeSelectedCard();  
    }
    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    onUpdateActionButtons(stateName, args) {
        if (this.bga.players.isCurrentPlayerActive()) {
            switch (stateName) {
                case 'playCard':
                    this.bga.statusBar.addActionButton(_('Cancel'), () => this.bga.actions.performAction('actCancel'), { color: 'secondary' });
                    break;
                case 'keepCard':
                    const labels = [
                        _("Keep the card on the table"),
                        _("Keep the new card"),
                    ];
                    [0, 1].forEach(index => {
                        this.bga.statusBar.addActionButton(`${labels[index]}<br><div id="keepCard${index}"></div>`, () => this.bga.actions.performAction('actKeepCard', { index }), { id: `keepCard${index}_button` });
                        this.cardsManager.setForHelp(args.cards[index], `keepCard${index}`);
                    });
                    this.bga.statusBar.addActionButton(_('Cancel'), () => this.bga.actions.performAction('actCancel'), { color: 'secondary' });
                    break;
                case 'BeforeEndRound':
                    this.bga.statusBar.addActionButton(_("Seen"), () => this.bga.actions.performAction('actSeen'));
                    break;
            }
        }
    }
    ///////////////////////////////////////////////////
    //// Utility methods
    ///////////////////////////////////////////////////
    setTooltip(id, html) {
        this.bga.gameui.addTooltipHtml(id, html, this.TOOLTIP_DELAY);
    }
    setTooltipToClass(className, html) {
        this.bga.gameui.addTooltipHtmlToClass(className, html, this.TOOLTIP_DELAY);
    }
    getPlayerId() {
        return this.bga.players.getCurrentPlayerId();
    }
    getPlayerColor(playerId) {
        return this.gamedatas.players[playerId].color;
    }
    getPlayer(playerId) {
        return Object.values(this.gamedatas.players).find(player => Number(player.id) == playerId);
    }
    getPlayerTable(playerId) {
        return this.playersTables.find(playerTable => playerTable.playerId === playerId);
    }
    getCurrentPlayerTable() {
        return this.playersTables.find(playerTable => playerTable.playerId === this.getPlayerId());
    }
    getOrderedPlayers(gamedatas) {
        const players = Object.values(gamedatas.players).sort((a, b) => a.playerNo - b.playerNo);
        const playerIndex = players.findIndex(player => Number(player.id) === this.bga.players.getCurrentPlayerId());
        const orderedPlayers = playerIndex > 0 ? [...players.slice(playerIndex), ...players.slice(0, playerIndex)] : players;
        return orderedPlayers;
    }
    createPlayerTables(gamedatas) {
        const orderedPlayers = this.getOrderedPlayers(gamedatas);
        orderedPlayers.forEach(player => this.createPlayerTable(gamedatas, Number(player.id)));
    }
    createPlayerTable(gamedatas, playerId) {
        const table = new PlayerTable(this, gamedatas.players[playerId]);
        this.playersTables.push(table);
    }
    onTableCardClick(card) {
        this.chooseCard(card.id);
    }
    onSpaceClick(row, column) {
        this.bga.actions.performAction('actPlayCard', { row, column });
    }
    getHelpHtml() {
        let html = `
        <div id="help-popin">
            <h1>${_("Validated cards")}</h1>
            ${_("Each validated card earns as many points as the number on it.")}

            <h1>${_("Symbols")}</h1>
            ${_("A spiral earns 1 point.")}<br>
            ${_("A cross makes the player lose 1 point.")}<br>
            ${_("Spiral")} / <div class="color-icon" data-row="0"></div> : ${_("1 spiral for each faceup card of the indicated color.")}<br><br>
        `;
        if (this.gamedatas.flowerPowerExpansion) {
            html += `
                ${_("Cross")} / <div class="color-icon" data-row="1"></div> : ${_("1 cross for each faceup card of the indicated color.")}<br><br>`;
        }
        html += `
            ${_("<strong>Note:</strong> All faceup cards are taken into account, whether they are validated or not.")}
        `;
        if (this.gamedatas.flowerPowerExpansion) {
            html += `
                <h1>${_("Facedown cards")}</h1>
                <i>${_('Only with the Flower Power expansion')}</i><br>
                ${_("Each facedown card that is not covered by a faceup card earns you 5 spirals.")}<br><br>
                ${_("Spiral(s) / facedown cards")} : ${_("Some cards earn you 1 to 3 spirals for each additional uncovered facedown card.")}
            `;
        }
        html += `
            <h1>${_("The player’s largest color zone")}</h1>
            ${_("A color zone is made up of at least 2 cards of the same color touching along a side. Diagonals do not count. Each card that is part of the player’s largest zone earns:")}
            <ul>
            ${[1, 2, 3].map(roundNumber => `<li>${_("${points} points in round ${round}")}</li>`.replace('${points}', `${roundNumber + 1}`).replace('${round}', `${roundNumber}`)).join('')}
            </ul>
            ${_("<strong>Note:</strong> All faceup cards are taken into account, whether they are validated or not.")}
            <br><br>
            ${_("A multi-colored card has all the colors at the same time. This means that it counts for the player’s color zone of course, but also for all their special cards as well.")}
        `;
        if (this.gamedatas.littleGiantsExpansion) {
            html += `<h1>${_("Little Giants scoring")}</h1>
            <div class="little-giants-effects">`;
            html += [1, 2, 3, 4, 5].map(effect => `            
                <div class="effect-img" data-effect="${effect}"></div>
                <div>${this.cardsManager.getEffect(effect)}</div>
            `).join('');
            html += `</div>`;
        }
        html += `</div>`;
        return html;
    }
    getColorAddHtml() {
        return [1, 2, 3, 4].map((number, index) => `<div class="color-icon" data-row="${index}"></div><span class="label"> ${this.cardsManager.getColor(number)}</span>`).join('');
    }
    setRoundResultForPlayer(playerId, detailledScore, round) {
        if (!document.getElementById(`points-${round}-${playerId}`)) {
            const emptyRoundResult = [];
            Object.keys(this.gamedatas.players).forEach(id => emptyRoundResult[id] = null);
            this.setRoundResult(emptyRoundResult, round);
        }
        Object.entries(detailledScore).forEach(([key, value]) => {
            const div = document.getElementById(`${key}-${round}-${playerId}`);
            if (div) {
                div.innerText = `${value}`;
            }
        });
        this.setRoundHighlightsForPlayer(playerId, detailledScore);
    }
    setRoundHighlightsForPlayer(playerId, detailledScore) {
        new Set([...Object.keys(detailledScore.computedSpiralsPerCard ?? []), ...Object.keys(detailledScore.computedCrossesPerCard ?? [])].map(Number)).forEach(cardId => {
            const cardFront = this.cardsManager.getCardElement({ id: cardId })?.querySelector('.front');
            cardFront?.insertAdjacentHTML('beforeend', `<div class="score-detail-per-card">
                <div>${detailledScore.computedSpiralsPerCard[cardId] ?? ''}</div>
                <div>${detailledScore.computedCrossesPerCard[cardId] ?? ''}</div>
            </div>`);
        });
        if (detailledScore.largestColorZoneColor && detailledScore.largestColorZoneCardCoordinates) {
            let color = 'transparent';
            switch (detailledScore.largestColorZoneColor) {
                case 1:
                    color = '#34a7e1';
                    break;
                case 2:
                    color = '#73b82c';
                    break;
                case 3:
                    color = '#f7cc00';
                    break;
                case 4:
                    color = '#d31126';
                    break;
            }
            document.getElementById(`player-table-${playerId}-cards`).style.setProperty('--largest-zone-color', color);
            detailledScore.largestColorZoneCardCoordinates.forEach(coordinate => document.getElementById(`player-table-${playerId}-cards-${coordinate[0]}-${coordinate[1]}`)?.classList.add('largest-zone-slot'));
        }
    }
    setRoundResult(roundResult, round, latestRound = true) {
        if (this.gamedatas.roundResult[round - 1]) {
            this.setRoundResult(this.gamedatas.roundResult[round - 1], round - 1, false);
        }
        const playersIds = Object.keys(roundResult).map(Number);
        let html = `<table class='round-result'>
            <tr><th class="empty"></th><th colspan="${playersIds.length}" class="round">${_("Round")} <strong>${round}</strong></th></tr>
            <tr><th class="empty"></th>${playersIds.map(playerId => `<th class="name"><strong style='color: #${this.getPlayer(playerId).color};'>${this.getPlayer(playerId).name}</strong></th>`).join('')}</tr>
            <tr><th class="type"><div class="score-icon validated"></div></th>${playersIds.map(playerId => `<td id="validatedCardPoints-${round}-${playerId}">${roundResult[playerId]?.validatedCardPoints ?? ''}</td>`).join('')}</tr>
            <tr><th class="type"><div class="score-icon zone" data-round="${round}"></div></th>${playersIds.map(playerId => `<td id="largestColorZonePoints-${round}-${playerId}">${roundResult[playerId]?.largestColorZonePoints ?? ''}</td>`).join('')}</tr>
            <tr><th class="type"><div class="score-icon spirals"></div></th>${playersIds.map(playerId => `<td id="spiralsAndCrossesPoints-${round}-${playerId}">${roundResult[playerId]?.spiralsAndCrossesPoints ?? ''}</td>`).join('')}</tr>
            ${this.gamedatas.flowerPowerExpansion ? `<tr><th class="type"><div class="score-icon facedown"></div></th>${playersIds.map(playerId => `<td id="facedownCardsPoints-${round}-${playerId}">${roundResult[playerId]?.facedownCardsPoints ?? ''}</td>`).join('')}</tr>` : ``}
            <tr><th class="type"><div class="score-icon sum"></div></th>${playersIds.map(playerId => `<th class="sum" id="points-${round}-${playerId}">${roundResult[playerId]?.points ?? ''}</th>`).join('')}</tr>
        </table>`;
        document.getElementById(`result`).insertAdjacentHTML('beforeend', html);
        if (latestRound && roundResult) {
            Object.entries(roundResult).filter(([playerId, detailledScore]) => !!detailledScore).forEach(([playerId, detailledScore]) => {
                this.setRoundHighlightsForPlayer(Number(playerId), detailledScore);
            });
        }
    }
    chooseCard(id) {
        this.bga.actions.performAction('actChooseCard', { id });
    }
    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications
    /*
        setupNotifications:

        In this method, you associate each of your game notifications with your local method to handle it.

        Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                your pylos.game.php file.

    */
    setupNotifications() {
        //log( 'notifications subscriptions setup' );
        this.bga.notifications.setupPromiseNotifications();
    }
    async notif_newRound(args) {
        document.getElementById(`result`).innerHTML = ``;
        document.getElementById(`last-round`)?.remove();
        const { round } = args;
        this.roundCounter.toValue(round);
        await this.bga.gameui.wait(ANIMATION_MS);
    }
    async notif_newTurn(args) {
        const { cards } = args;
        await this.tableCenter.newTurn(cards);
    }
    async notif_playCard(args) {
        const { playerId, card, row, column } = args;
        const playerTable = this.getPlayerTable(playerId);
        await playerTable.playCard(card, row, column);
    }
    async notif_keepCard(args) {
        const { playerId, hiddenCard, visibleCard, row, column } = args;
        const playerTable = this.getPlayerTable(playerId);
        await playerTable.keepCard(hiddenCard, visibleCard, row, column);
    }
    /**
     * Show last turn banner.
     */
    async notif_lastTurn(animate = true) {
        dojo.place(`<div id="last-round">
            <span class="last-round-text ${animate ? 'animate' : ''}">${_("This is the final turn of the round!")}</span>
        </div>`, 'page-title');
    }
    async notif_score(args) {
        document.getElementById(`last-round`)?.remove();
        const { playerId, newScore, detailledScore, round } = args;
        this.bga.playerPanels.getScoreCounter(playerId).toValue(newScore);
        this.bga.gameui.displayScoring(`player-table-${playerId}-cards`, this.getPlayerColor(playerId), detailledScore.points, ANIMATION_MS * 3);
        this.setRoundResultForPlayer(playerId, detailledScore, round);
        await this.bga.gameui.wait(ANIMATION_MS * 3);
    }
    async notif_roundResult(args) {
        document.getElementById(`last-round`)?.remove();
        this.gamedatas.roundResult[args.round] = args.roundResult;
    }
    async notif_endRound(args) {
        const cards = this.tableCenter.getTableCards();
        this.playersTables.forEach(playerTable => cards.push(...playerTable.getAllCards()));
        await this.tableCenter.deck.addCards(cards, undefined, { visible: false });
        this.tableCenter.deck.setCardNumber(args.remainingCardsInDeck);
        document.querySelectorAll('.score-detail-per-card')?.forEach(elem => elem.remove());
        document.querySelectorAll('.largest-zone-slot')?.forEach(elem => elem.classList.remove('largest-zone-slot'));
        return await this.tableCenter.deck.shuffle();
    }
    /* This enable to inject translatable styled things to logs or action bar */
    /* @Override */
    bgaFormatText(log, args) {
        try {
            if (log && args && !args.processed) {
                ['roundNumber', 'value', 'incScore'].forEach(field => {
                    if (args[field] !== null && args[field] !== undefined && args[field][0] != '<') {
                        args[field] = `<strong>${_(args[field])}</strong>`;
                    }
                });
            }
        }
        catch (e) {
            console.error(log, args, "Exception thrown", e.stack);
        }
        return { log, args };
    }
}

export { Game };
