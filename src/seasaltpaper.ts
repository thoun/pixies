declare const define;
declare const ebg;
declare const $;
declare const dojo: Dojo;
declare const _;
declare const g_gamethemeurl;

const ANIMATION_MS = 500;
const ACTION_TIMER_DURATION = 5;

const LOCAL_STORAGE_ZOOM_KEY = 'Pixies-zoom';

const POINTS_FOR_PLAYERS = [null, null, 40, 35, 30];

class Pixies implements PixiesGame {
    public animationManager: AnimationManager;
    public cardsManager: CardsManager;

    private zoomManager: ZoomManager;
    private gamedatas: PixiesGamedatas;
    private stacks: Stacks;
    private playersTables: PlayerTable[] = [];
    private selectedCards: Card[];
    private selectedStarfishCards: Card[];
    private lastNotif: any;
    private handCounters: Counter[] = [];

    private discardStock: LineStock<Card>;
    
    private TOOLTIP_DELAY = document.body.classList.contains('touch-device') ? 1500 : undefined;

    constructor() {
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

    public setup(gamedatas: PixiesGamedatas) {
        log( "Starting game setup" );

        if (gamedatas.expansion) {
            (this as any).dontPreloadImage('background.jpg');
            document.getElementsByTagName('html')[0].classList.add('expansion');
        } else {
            (this as any).dontPreloadImage('background-expansion.jpg');
        }
        
        this.gamedatas = gamedatas;

        log('gamedatas', gamedatas);

        this.animationManager = new AnimationManager(this);
        this.cardsManager = new CardsManager(this);
        this.stacks = new Stacks(this, this.gamedatas);
        this.createPlayerPanels(gamedatas);
        this.createPlayerTables(gamedatas);
        
        this.zoomManager = new ZoomManager({
            element: document.getElementById('full-table'),
            smooth: false,
            zoomControls: {
                color: 'white',
            },
            localStorageZoomKey: LOCAL_STORAGE_ZOOM_KEY,
            onDimensionsChange: () => this.onTableCenterSizeChange(),
        });

        this.setupNotifications();
        this.setupPreferences();
        this.addHelp();

        (this as any).onScreenWidthChange = () => {
            this.updateTableHeight();
        };

        log( "Ending game setup" );
    }

    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    public onEnteringState(stateName: string, args: any) {
        log('Entering state: ' + stateName, args.args);

        switch (stateName) {
            case 'takeCards':
                this.onEnteringTakeCards(args);
                break;
            case 'chooseCard':
                this.onEnteringChooseCard(args.args);
                break;
            case 'putDiscardPile':
                this.onEnteringPutDiscardPile(args.args);
                break;
            case 'playCards':
                this.onEnteringPlayCards();
                break;
            case 'chooseDiscardPile':
                this.onEnteringChooseDiscardPile();
                break;
            case 'chooseDiscardCard':
                this.onEnteringChooseDiscardCard(args.args);
                break;
            case 'chooseOpponent':
                this.onEnteringChooseOpponent(args.args);
                break;
        }
    }
    
    private setGamestateDescription(property: string = '') {
        const originalState = this.gamedatas.gamestates[this.gamedatas.gamestate.id];
        this.gamedatas.gamestate.description = `${originalState['description' + property]}`; 
        this.gamedatas.gamestate.descriptionmyturn = `${originalState['descriptionmyturn' + property]}`;
        (this as any).updatePageTitle();
    }
    
    private onEnteringTakeCards(argsRoot: { args: EnteringTakeCardsArgs, active_player: string }) {
        const args = argsRoot.args;

        this.clearLogs(argsRoot.active_player);

        if (args.forceTakeOne) {
            this.setGamestateDescription('ForceTakeOne');
        } else if (!args.canTakeFromDiscard.length) {
            this.setGamestateDescription('NoDiscard');
        }

        if ((this as any).isCurrentPlayerActive()) {
            this.stacks.makeDeckSelectable(args.canTakeFromDeck);
            this.stacks.makeDiscardSelectable(!args.forceTakeOne);
        }
    }
    
    private onEnteringChooseCard(args: EnteringChooseCardArgs) {
        const currentPlayer = (this as any).isCurrentPlayerActive();
        this.stacks.showPickCards(true, args._private?.cards ?? args.cards, currentPlayer);
        if (currentPlayer) {
            setTimeout(() => this.stacks.makePickSelectable(true), 500);
        } else {
            this.stacks.makePickSelectable(false);
        }        
        this.stacks.deck.setCardNumber(args.remainingCardsInDeck, args.deckTopCard);
    }
    
    private onEnteringPutDiscardPile(args: EnteringChooseCardArgs) {
        const currentPlayer = (this as any).isCurrentPlayerActive();
        this.stacks.showPickCards(true, args._private?.cards ?? args.cards, currentPlayer);
        this.stacks.makeDiscardSelectable(currentPlayer);
    }

    private onEnteringPlayCards() {
        this.stacks.showPickCards(false);
        this.selectedCards = [];
        this.selectedStarfishCards = [];

        if ((this as any).isCurrentPlayerActive()) {
            this.getCurrentPlayerTable()?.setSelectable(true);
            this.updateDisabledPlayCards();
        }
    }
    
    private onEnteringChooseDiscardPile() {
        this.stacks.makeDiscardSelectable((this as any).isCurrentPlayerActive());
    }
    
    private onEnteringChooseDiscardCard(args: EnteringChooseCardArgs) {
        const currentPlayer = (this as any).isCurrentPlayerActive();
        const cards = args._private?.cards || args.cards;
        const pickDiv = document.getElementById('discard-pick');
        pickDiv.innerHTML = '';
        pickDiv.dataset.visible = 'true';

        if (!this.discardStock) {
            this.discardStock = new LineStock<Card>(this.cardsManager, pickDiv, { gap: '0px' });
            this.discardStock.onCardClick = card => this.chooseDiscardCard(card.id);
        }

        cards?.forEach(card => {
            this.discardStock.addCard(card, { fromStock: this.stacks.getDiscardDeck(args.discardNumber) });
        });
        if (currentPlayer) {
            this.discardStock.setSelectionMode('single');
        }

        this.updateTableHeight();
    }
    
    private onEnteringChooseOpponent(args: EnteringChooseOpponentArgs) {
        if ((this as any).isCurrentPlayerActive()) {
            args.playersIds.forEach(playerId => 
                document.getElementById(`player-table-${playerId}-hand-cards`).dataset.canSteal = 'true'
            );
        }
    }

    public onLeavingState(stateName: string) {
        log( 'Leaving state: '+stateName );

        switch (stateName) {
            case 'takeCards':
                this.onLeavingTakeCards();
                break;
            case 'chooseCard':
                this.onLeavingChooseCard();
                break;
            case 'putDiscardPile':
                this.onLeavingPutDiscardPile();
                break;
            case 'playCards':
                this.onLeavingPlayCards();
                break;
            case 'chooseDiscardCard':
                this.onLeavingChooseDiscardCard();
                break;
            case 'chooseOpponent':
                this.onLeavingChooseOpponent();
                break;
        }
    }

    private onLeavingTakeCards() {
        this.stacks.makeDeckSelectable(false);
        this.stacks.makeDiscardSelectable(false);
    }
    
    private onLeavingChooseCard() {
        this.stacks.makePickSelectable(false);
    }

    private onLeavingPutDiscardPile() {
        this.stacks.makeDiscardSelectable(false);
    }

    private onLeavingPlayCards() {
        this.selectedCards = null;
        this.selectedStarfishCards = null;
        this.getCurrentPlayerTable()?.setSelectable(false);
    }

    private onLeavingChooseDiscardCard() {
        const pickDiv = document.getElementById('discard-pick');
        pickDiv.dataset.visible = 'false';
        this.discardStock?.removeAll();
        this.updateTableHeight();
    }

    private onLeavingChooseOpponent() {
        (Array.from(document.querySelectorAll('[data-can-steal]')) as HTMLElement[]).forEach(elem => elem.dataset.canSteal = 'false');
    }

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    public onUpdateActionButtons(stateName: string, args: any) {
        if ((this as any).isCurrentPlayerActive()) {
            switch (stateName) {
                
                case 'takeCards':
                    if (args.forceTakeOne) {
                        (this as any).addActionButton(`takeFirstCard_button`, _("Take the first card"), () => this.takeCardsFromDeck());
                    }
                    break;
                case 'playCards':
                    const playCardsArgs = args as EnteringPlayCardsArgs;
                    (this as any).addActionButton(`playCards_button`, _("Play selected cards"), () => this.playSelectedCards());
                    if (playCardsArgs.hasFourMermaids) {
                        (this as any).addActionButton(`endGameWithMermaids_button`, _("Play the four Mermaids"), () => this.endGameWithMermaids(), null, true, 'red');
                    }
                    (this as any).addActionButton(`endTurn_button`, _("End turn"), () => this.endTurn());
                    if (playCardsArgs.canCallEndRound) {
                        (this as any).addActionButton(`endRound_button`, _('End round') + ' ("' + _('LAST CHANCE') + '")', () => this.endRound(), null, null, 'red');
                        (this as any).addActionButton(`immediateEndRound_button`, _('End round') + ' ("' + _('STOP') + '")', () => this.immediateEndRound(), null, null, 'red');

                        this.setTooltip(`endRound_button`, `${_("Say <strong>LAST CHANCE</strong> if you are willing to take the bet of having the most points at the end of the round. The other players each take a final turn (take a card + play cards) which they complete by revealing their hand, which is now protected from attacks. Then, all players count the points on their cards (in their hand and in front of them).")}<br><br>
                        ${_("If your hand is higher or equal to that of your opponents, bet won! You score the points for your cards + the color bonus (1 point per card of the color they have the most of). Your opponents only score their color bonus.")}<br><br>
                        ${_("If your score is less than that of at least one opponent, bet lost! You score only the color bonus. Your opponents score points for their cards.")}`);
                        this.setTooltip(`immediateEndRound_button`, _("Say <strong>STOP</strong> if you do not want to take a risk. All players reveal their hands and immediately score the points on their cards (in their hand and in front of them)."));
                    }
                    dojo.addClass(`playCards_button`, `disabled`);
                    /*if (!playCardsArgs.canCallEndRound) {
                        dojo.addClass(`endRound_button`, `disabled`);
                        dojo.addClass(`immediateEndRound_button`, `disabled`);
                    }*/
                    
                    if (!playCardsArgs.canDoAction) {
                        this.startActionTimer('endTurn_button', ACTION_TIMER_DURATION + Math.round(3 * Math.random()));
                    }
                    break;
                /*case 'chooseOpponent':
                    const chooseOpponentArgs = args as EnteringChooseOpponentArgs;
        
                    chooseOpponentArgs.playersIds.forEach(playerId => {
                        const player = this.getPlayer(playerId);
                        (this as any).addActionButton(`choosePlayer${playerId}-button`, player.name, () => this.chooseOpponent(playerId));
                        document.getElementById(`choosePlayer${playerId}-button`).style.border = `3px solid #${player.color}`;
                    });
                    break;*/
                case 'beforeEndRound':
                    (this as any).addActionButton(`seen_button`, _("Seen"), () => this.seen());
                    break;
            }
        }
    }

    ///////////////////////////////////////////////////
    //// Utility methods


    ///////////////////////////////////////////////////

    public setTooltip(id: string, html: string) {
        (this as any).addTooltipHtml(id, html, this.TOOLTIP_DELAY);
    }
    public setTooltipToClass(className: string, html: string) {
        (this as any).addTooltipHtmlToClass(className, html, this.TOOLTIP_DELAY);
    }

    public isExpansion(): boolean {
        return this.gamedatas.expansion;
    }

    public getPlayerId(): number {
        return Number((this as any).player_id);
    }

    public getPlayerColor(playerId: number): string {
        return this.gamedatas.players[playerId].color;
    }

    private getPlayer(playerId: number): PixiesPlayer {
        return Object.values(this.gamedatas.players).find(player => Number(player.id) == playerId);
    }

    private getPlayerTable(playerId: number): PlayerTable {
        return this.playersTables.find(playerTable => playerTable.playerId === playerId);
    }

    private getCurrentPlayerTable(): PlayerTable | null {
        return this.playersTables.find(playerTable => playerTable.playerId === this.getPlayerId());
    }

    public updateTableHeight() {
        this.zoomManager?.manualHeightUpdate();
    }

    private onTableCenterSizeChange() {
        const maxWidth = document.getElementById('full-table').clientWidth;
        const tableCenterWidth = document.getElementById('table-center').clientWidth + 20;
        const playerTableWidth = 650 + 20;
        const tablesMaxWidth = maxWidth - tableCenterWidth;
     
        let width = 'unset';
        if (tablesMaxWidth < playerTableWidth * this.gamedatas.playerorder.length) {
            const reduced = (Math.floor(tablesMaxWidth / playerTableWidth) * playerTableWidth);
            if (reduced > 0) {
                width = `${reduced}px`;
            }
        }
        document.getElementById('tables').style.width = width;
    }

    private setupPreferences() {
        // Extract the ID and value from the UI control
        const onchange = (e) => {
          var match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/);
          if (!match) {
            return;
          }
          var prefId = +match[1];
          var prefValue = +e.target.value;
          (this as any).prefs[prefId].value = prefValue;
        }
        
        // Call onPreferenceChange() when any value changes
        dojo.query(".preference_control").connect("onchange", onchange);
        
        // Call onPreferenceChange() now
        dojo.forEach(
          dojo.query("#ingame_menu_content .preference_control"),
          el => onchange({ target: el })
        );
    }

    private getOrderedPlayers(gamedatas: PixiesGamedatas) {
        const players = Object.values(gamedatas.players).sort((a, b) => a.playerNo - b.playerNo);
        const playerIndex = players.findIndex(player => Number(player.id) === Number((this as any).player_id));
        const orderedPlayers = playerIndex > 0 ? [...players.slice(playerIndex), ...players.slice(0, playerIndex)] : players;
        return orderedPlayers;
    }

    private createPlayerPanels(gamedatas: PixiesGamedatas) {
        let endPoints = POINTS_FOR_PLAYERS[Object.keys(gamedatas.players).length];
        if (gamedatas.doublePoints) {
            endPoints *= 2;
        }

        Object.values(gamedatas.players).forEach(player => {
            const playerId = Number(player.id);   

            // show end game points
            dojo.place(`<span class="end-game-points">&nbsp;/&nbsp;${endPoints}</span>`, `player_score_${playerId}`, 'after');

            // hand cards counter
            dojo.place(`<div class="counters">
                <div id="playerhand-counter-wrapper-${player.id}" class="playerhand-counter">
                    <div class="player-hand-card"></div> 
                    <span id="playerhand-counter-${player.id}"></span>
                </div>
            </div>`, `player_board_${player.id}`);

            const handCounter = new ebg.counter();
            handCounter.create(`playerhand-counter-${playerId}`);
            handCounter.setValue(player.handCards.length);
            this.handCounters[playerId] = handCounter;
        });

        this.setTooltipToClass('playerhand-counter', _('Number of cards in hand'));
    }

    private createPlayerTables(gamedatas: PixiesGamedatas) {
        const orderedPlayers = this.getOrderedPlayers(gamedatas);

        orderedPlayers.forEach(player => 
            this.createPlayerTable(gamedatas, Number(player.id))
        );
    }

    private createPlayerTable(gamedatas: PixiesGamedatas, playerId: number) {
        const table = new PlayerTable(this, gamedatas.players[playerId]);
        this.playersTables.push(table);
    }

    private updateDisabledPlayCards() {
        this.getCurrentPlayerTable()?.updateDisabledPlayCards(this.selectedCards, this.selectedStarfishCards, this.gamedatas.gamestate.args.playableDuoCards);
        document.getElementById(`playCards_button`)?.classList.toggle(`disabled`, this.selectedCards.length != 2 || this.selectedStarfishCards.length > 1);
    }
    
    public onCardClick(card: Card): void {
        const cardDiv = document.getElementById(`card-${card.id}`) ?? document.getElementById(`ssp-card-${card.id}`);
        const parentDiv = cardDiv.parentElement;

        if (cardDiv.classList.contains('bga-cards_disabled-card')) {
            return;
        }

        switch (this.gamedatas.gamestate.name) {
            case 'takeCards':
                if (parentDiv.dataset.discard) {
                    this.takeCardFromDiscard(Number(parentDiv.dataset.discard));
                }
                break;
            case 'chooseCard':
                if (parentDiv.id == 'pick') {
                    this.chooseCard(card.id);
                }
                break;
            case 'playCards':
                if (parentDiv.dataset.myHand == `true`) {
                    const array = card.category == SPECIAL && card.family == STARFISH ? this.selectedStarfishCards : this.selectedCards;
                    if (array.some(c => c.id == card.id)) {
                        array.splice(array.findIndex(c => c.id == card.id), 1);
                        cardDiv.classList.remove('bga-cards_selected-card');
                    } else {
                        array.push(card);
                        cardDiv.classList.add('bga-cards_selected-card');
                    }
                    this.updateDisabledPlayCards();
                }
                break;
            case 'chooseDiscardCard':
                if (parentDiv.id == 'discard-pick') {
                    this.chooseDiscardCard(card.id);
                }
                break;
            case 'chooseOpponent':
                const chooseOpponentArgs = this.gamedatas.gamestate.args as EnteringChooseOpponentArgs;
                if (parentDiv.dataset.currentPlayer == 'false') {
                    const stealPlayerId = Number(parentDiv.dataset.playerId);
                    if (chooseOpponentArgs.playersIds.includes(stealPlayerId)) {
                        this.chooseOpponent(stealPlayerId);
                    }
                }
                break;
        }
    }

    public onDiscardPileClick(number: number): void {
        switch (this.gamedatas.gamestate.name) {
            case 'takeCards':
                this.takeCardFromDiscard(number);
                break;
            case 'putDiscardPile':
                this.putDiscardPile(number);
                break;
            case 'chooseDiscardPile':
                this.chooseDiscardPile(number);
                break;
        }
    }

    private playSelectedCards() {
        if (this.selectedCards.length == 2) {
            if (this.selectedStarfishCards.length > 0) {
                if (this.selectedStarfishCards.length == 1) {
                    this.playCardsTrio(this.selectedCards.map(card => card.id), this.selectedStarfishCards[0].id);
                }
            } else {
                this.playCards(this.selectedCards.map(card => card.id));
            }
        }
    }

    private addHelp() {
        const quantities = [
            9, 9,
            8, 8,
            6, 4,
            4, 4,
            3, 2,
            1,
        ];

        if (this.isExpansion()) {
            [6, 9, 2, 4, 0, 1, 3, 5].forEach(index => quantities[index] += 1);
        }

        let labels = [
            _('Dark blue'),
            _('Light blue'),
            _('Black'),
            _('Yellow'),
            _('Green'),
            _('White'),
            _('Purple'),
            _('Gray'),
            _('Light orange'),
            _('Pink'),
            _('Orange'),
        ].map((label, index) => `
            <span class="label" data-row="${Math.floor(index / 2)}" data-column="${Math.floor(index % 2)}">${label}</span>
            <span class="quantity" data-row="${Math.floor(index / 2)}" data-column="${Math.floor(index % 2)}">&times; ${quantities[index]}</span>
            `).join('');
        dojo.place(`
            <button id="pixies-help-button">?</button>
            <button id="color-help-button" data-folded="true">${labels}</button>
        `, 'left-side');
        document.getElementById('pixies-help-button').addEventListener('click', () => this.showHelp());
        const helpButton = document.getElementById('color-help-button');
        helpButton.addEventListener('click', () => helpButton.dataset.folded = helpButton.dataset.folded == 'true' ? 'false' : 'true');
    }

    private showHelp() {
        const helpDialog = new ebg.popindialog();
        helpDialog.create('pixiesHelpDialog');
        helpDialog.setTitle(_("Card details").toUpperCase());

        const expansion = this.isExpansion();

        const duoCardsNumbers = expansion ? [1, 2, 3, 4, 5, 6, 7] : [1, 2, 3, 4, 5];
        const multiplierNumbers = expansion ? [1, 2, 3, 4, 5] : [1, 2, 3, 4];

        const duoCards = duoCardsNumbers.map(family => `
        <div class="help-section">
            <div id="help-pair-${family}"></div>
            <div>${this.cardsManager.getTooltip(2, family)}</div>
        </div>
        `).join('');

        const duoSection = `
        ${duoCards}
        ${_("Note: The points for duo cards count whether the cards have been played or not. However, the effect is only applied when the player places the two cards in front of them.")}`;

        const mermaidSection = `
        <div class="help-section">
            <div id="help-mermaid"></div>
            <div>${this.cardsManager.getTooltip(1)}</div>
        </div>`;

        const collectorSection = [1, 2, 3, 4].map(family => `
        <div class="help-section">
            <div id="help-collector-${family}"></div>
            <div>${this.cardsManager.getTooltip(3, family)}</div>
        </div>
        `).join('');

        const multiplierSection = multiplierNumbers.map(family => `
        <div class="help-section">
            <div id="help-multiplier-${family}"></div>
            <div>${this.cardsManager.getTooltip(4, family)}</div>
        </div>
        `).join('');
        
        let html = `
        <div id="help-popin">
            ${_("<strong>Important:</strong> When it is said that the player counts or scores the points on their cards, it means both those in their hand and those in front of them.")}

            <h1>${_("Duo cards")}</h1>
            ${duoSection}
            <h1>${_("Mermaid cards")}</h1>
            ${mermaidSection}
            <h1>${_("Collector cards")}</h1>
            ${collectorSection}
            <h1>${_("Point Multiplier cards")}</h1>
            ${multiplierSection}
        `;

        if (expansion) {
            const specialSection = [1, 2].map(family => `
            <div class="help-section">
                <div id="help-special-${family}"></div>
                <div>${this.cardsManager.getTooltip(5, family)}</div>
            </div>
            `).join('');

            html += `
                <h1>${_("Special cards")}</h1>
                ${specialSection}
            `;

        }
        html += `
        </div>
        `;
        
        // Show the dialog
        helpDialog.setContent(html);

        helpDialog.show();

        // pair
        const duoCardsPairs = expansion ? [[1, 1], [2, 2], [3, 3], [4, 4], [5, 5], [6, 6], [7, 3]] : [[1, 1], [2, 2], [3, 3], [4, 4], [5, 5]];
        duoCardsPairs.forEach(([family, color]) => this.cardsManager.setForHelp({id: 1020 + family, category: 2, family, color, index: 0 } as any, `help-pair-${family}`));
        // mermaid
        this.cardsManager.setForHelp({id: 1010, category: 1 } as any, `help-mermaid`);
        // collector
        [[1, 1], [2, 2], [3, 6], [4, 9]].forEach(([family, color]) => this.cardsManager.setForHelp({id: 1030 + family, category: 3, family, color, index: 0 } as any, `help-collector-${family}`));
        // multiplier
        multiplierNumbers.forEach(family => this.cardsManager.setForHelp({id: 1040 + family, category: 4, family } as any, `help-multiplier-${family}`));
        if (expansion) {
            // special
            [[1, 1], [2, 0]].forEach(([family, color]) => this.cardsManager.setForHelp({id: 1050 + family, category: 5, family, color } as any, `help-special-${family}`));
        }
    }

    public takeCardsFromDeck() {
        if(!(this as any).checkAction('takeCardsFromDeck')) {
            return;
        }

        this.takeAction('takeCardsFromDeck');
    }

    public takeCardFromDiscard(discardNumber: number) {
        if(!(this as any).checkAction('takeCardFromDiscard')) {
            return;
        }

        this.takeAction('takeCardFromDiscard', {
            discardNumber
        });
    }

    public chooseCard(id: number) {
        if(!(this as any).checkAction('chooseCard')) {
            return;
        }

        this.takeAction('chooseCard', {
            id
        });
    }

    public putDiscardPile(discardNumber: number) {
        if(!(this as any).checkAction('putDiscardPile')) {
            return;
        }

        this.takeAction('putDiscardPile', {
            discardNumber
        });
    }

    public playCards(ids: number[]) {
        if(!(this as any).checkAction('playCards')) {
            return;
        }

        this.takeAction('playCards', {
            'id1': ids[0],
            'id2': ids[1],
        });
    }

    public playCardsTrio(ids: number[], starfishId: number) {
        if(!(this as any).checkAction('playCardsTrio')) {
            return;
        }

        this.takeAction('playCardsTrio', {
            'id1': ids[0],
            'id2': ids[1],
            'starfishId': starfishId
        });
    }

    public chooseDiscardPile(discardNumber: number) {
        if(!(this as any).checkAction('chooseDiscardPile')) {
            return;
        }

        this.takeAction('chooseDiscardPile', {
            discardNumber
        });
    }

    public chooseDiscardCard(id: number) {
        if(!(this as any).checkAction('chooseDiscardCard')) {
            return;
        }

        this.takeAction('chooseDiscardCard', {
            id
        });
    }

    public chooseOpponent(id: number) {
        if(!(this as any).checkAction('chooseOpponent')) {
            return;
        }

        this.takeAction('chooseOpponent', {
            id
        });
    }

    public endTurn() {
        if(!(this as any).checkAction('endTurn')) {
            return;
        }

        this.takeAction('endTurn');
    }

    public endGameWithMermaids() {
        if(!(this as any).checkAction('endGameWithMermaids')) {
            return;
        }

        this.takeAction('endGameWithMermaids');
    }

    public endRound() {
        if(!(this as any).checkAction('endRound')) {
            return;
        }

        this.takeAction('endRound');
    }

    public immediateEndRound() {
        if(!(this as any).checkAction('immediateEndRound')) {
            return;
        }

        this.takeAction('immediateEndRound');
    }

    public seen() {
        if(!(this as any).checkAction('seen')) {
            return;
        }

        this.takeAction('seen');
    }

    public takeAction(action: string, data?: any) {
        data = data || {};
        data.lock = true;
        (this as any).ajaxcall(`/pixies/pixies/${action}.html`, data, this, () => {});
    }

    private startActionTimer(buttonId: string, time: number) {
        if (Number((this as any).prefs[202]?.value) === 2) {
            return;
        }

        const button = document.getElementById(buttonId);
 
        let actionTimerId = null;
        const _actionTimerLabel = button.innerHTML;
        let _actionTimerSeconds = time;
        const actionTimerFunction = () => {
          const button = document.getElementById(buttonId);
          if (button == null || button.classList.contains('disabled')) {
            window.clearInterval(actionTimerId);
          } else if (_actionTimerSeconds-- > 1) {
            button.innerHTML = _actionTimerLabel + ' (' + _actionTimerSeconds + ')';
          } else {
            window.clearInterval(actionTimerId);
            button.click();
          }
        };
        actionTimerFunction();
        actionTimerId = window.setInterval(() => actionTimerFunction(), 1000);
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

        dojo.connect((this as any).notifqueue, 'addToLog', () => this.addLogClass());

        const notifs = [
            ['cardInDiscardFromDeck', ANIMATION_MS],
            ['cardInHandFromDiscard', ANIMATION_MS],
            ['cardInHandFromDiscardCrab', ANIMATION_MS],
            ['cardInHandFromPick', ANIMATION_MS],
            ['cardInHandFromDeck', ANIMATION_MS],
            ['cardInDiscardFromPick', ANIMATION_MS],
            ['cardsInDeckFromPick', ANIMATION_MS],
            ['playCards', undefined],
            ['stealCard', undefined],
            ['revealHand', ANIMATION_MS * 2],
            ['announceEndRound', ANIMATION_MS * 2],
            ['betResult', ANIMATION_MS * 2],
            ['endRound', undefined],
            ['score', ANIMATION_MS * 3],
            ['newRound', 1],
            ['updateCardsPoints', 1],
            ['emptyDeck', 1],
            ['reshuffleDeck', undefined],
        ];
    
        notifs.forEach((notif) => {
            dojo.subscribe(notif[0], this, (notifDetails: Notif<any>) => {
                log(`notif_${notif[0]}`, notifDetails.args);

                const promise = this[`notif_${notif[0]}`](notifDetails.args);

                // tell the UI notification ends, if the function returned a promise
                promise?.then(() => (this as any).notifqueue.onSynchronousNotificationEnd());
            });
            (this as any).notifqueue.setSynchronous(notif[0], notif[1]);
        });

        (this as any).notifqueue.setIgnoreNotificationCheck('cardInHandFromPick', (notif: Notif<NotifCardInHandFromPickArgs>) => 
            notif.args.playerId == this.getPlayerId() && !notif.args.card.category
        );
        (this as any).notifqueue.setIgnoreNotificationCheck('cardInHandFromDeck', (notif: Notif<NotifCardInHandFromPickArgs>) => 
            notif.args.playerId == this.getPlayerId() && !notif.args.card.category
        );
        (this as any).notifqueue.setIgnoreNotificationCheck('cardInHandFromDiscardCrab', (notif: Notif<NotifCardInHandFromDiscardArgs>) => 
            notif.args.playerId == this.getPlayerId() && !notif.args.card.category
        );
        (this as any).notifqueue.setIgnoreNotificationCheck('stealCard', (notif: Notif<NotifStealCardArgs>) => 
            [notif.args.playerId, notif.args.opponentId].includes(this.getPlayerId()) && !(notif.args as any).cardName
        );

        this.addLogClass();
        this.clearLogsInit(this.gamedatas.gamestate.active_player);
    }

    onPlaceLogOnChannel(msg) {
        var currentLogId = (this as any).notifqueue.next_log_id;
        var res = (this as any).inherited(arguments);
        this.lastNotif = {
          logId: currentLogId,
          msg,
        };
        return res;
    }
  
    addLogClass() {
        if (this.lastNotif == null) {
            return;
        }
  
        let notif = this.lastNotif;
        const elem = document.getElementById(`log_${notif.logId}`);
        if (elem) {
            let type = notif.msg.type;
            if (type == 'history_history') type = notif.msg.args.originalType;
    
            if (notif.msg.args.actionPlayerId) {
                elem.dataset.playerId = ''+notif.msg.args.actionPlayerId;
            }
        }
    }

    notif_cardInDiscardFromDeck(args: NotifCardInDiscardFromDeckArgs) {
        this.stacks.setDiscardCard(args.discardId, args.card, 1, document.getElementById('deck'));
        this.stacks.deck.setCardNumber(args.remainingCardsInDeck, args.deckTopCard);
        this.updateTableHeight();
    } 

    notif_cardInHandFromDiscard(args: NotifCardInHandFromDiscardArgs) {
        const card = args.card;
        const playerId = args.playerId;
        const discardNumber = args.discardId;
        const maskedCard = playerId == this.getPlayerId() ? card : { id: card.id } as Card;
        this.getPlayerTable(playerId).addCardsToHand([maskedCard]);
        this.handCounters[playerId].incValue(1);

        this.stacks.setDiscardCard(discardNumber, args.newDiscardTopCard, args.remainingCardsInDiscard);
        this.updateTableHeight();
    } 

    notif_cardInHandFromDiscardCrab(args: NotifCardInHandFromDiscardArgs) {
        const card = args.card;
        const playerId = args.playerId;
        const maskedCard = playerId == this.getPlayerId() ? card : { id: card.id } as Card;
        this.getPlayerTable(playerId).addCardsToHand([maskedCard]);
        this.handCounters[playerId].incValue(1);

        this.stacks.setDiscardCard(args.discardId, args.newDiscardTopCard, args.remainingCardsInDiscard);
        this.updateTableHeight();
    } 

    notif_cardInHandFromPick(args: NotifCardInHandFromPickArgs) {
        const playerId = args.playerId;
        this.getPlayerTable(playerId).addCardsToHand([args.card]);
        this.handCounters[playerId].incValue(1);
    }

    notif_cardInHandFromDeck(args: NotifCardInHandFromPickArgs) {
        const playerId = args.playerId;
        this.getPlayerTable(playerId).addCardsToHand([args.card], true);
        this.stacks.deck.setCardNumber(args.remainingCardsInDeck, args.deckTopCard)
        this.handCounters[playerId].incValue(1);
    }   

    notif_cardInDiscardFromPick(args: NotifCardInDiscardFromPickArgs) {
        const card = args.card;
        const discardNumber = args.discardId;
        this.cardsManager.setCardVisible(card, true);
        this.stacks.setDiscardCard(discardNumber, card, args.remainingCardsInDiscard);
        this.updateTableHeight();
    } 

    notif_cardsInDeckFromPick(args: NotifCardsInDeckFromPickArgs) {
        this.stacks.deck.addCards(args.cards, undefined, {
            visible: false,
        });
        this.stacks.deck.setCardNumber(args.remainingCardsInDeck, args.deckTopCard);
        this.updateTableHeight();
    }

    notif_score(args: NotifScoreArgs) {
        const playerId = args.playerId;
        (this as any).scoreCtrl[playerId]?.toValue(args.newScore);

        const incScore = args.incScore;
        if (incScore != null && incScore !== undefined) {
            (this as any).displayScoring(`player-table-${playerId}-table-cards`, this.getPlayerColor(playerId), incScore, ANIMATION_MS * 3);
        }

        if (args.details) {
            this.getPlayerTable(args.playerId).showScoreDetails(args.details);
        }
    }
    notif_newRound() {}

    notif_playCards(args: NotifPlayCardsArgs) {
        const playerId = args.playerId;
        const cards = args.cards;
        const playerTable = this.getPlayerTable(playerId);
        this.handCounters[playerId].incValue(-cards.length);
        return playerTable.addCardsToTable(cards);
    }

    notif_revealHand(args: NotifRevealHandArgs) {
        const playerId = args.playerId;
        const playerPoints = args.playerPoints;
        const playerTable = this.getPlayerTable(playerId);
        playerTable.showAnnouncementPoints(playerPoints);

        this.notif_playCards(args);
        this.handCounters[playerId].toValue(0);
    }

    notif_stealCard(args: NotifStealCardArgs) {
        const stealerId = args.playerId;
        const opponentId = args.opponentId;
        const card = args.card;
        this.getPlayerTable(opponentId).setSelectable(false);
        this.handCounters[opponentId].incValue(-1);
        this.handCounters[stealerId].incValue(1);
        return this.getPlayerTable(stealerId).addStolenCard(card, stealerId, opponentId);
    }

    notif_announceEndRound(args: NotifAnnounceEndRoundArgs) {
        this.getPlayerTable(args.playerId).showAnnouncement(args.announcement);
    }

    async notif_endRound(args: NotifEndRoundArgs) {
        const cards = this.stacks.getDiscardCards();

        this.playersTables.forEach(playerTable => {
            cards.push(...playerTable.getAllCards());
            this.handCounters[playerTable.playerId].setValue(0);
            playerTable.clearAnnouncement();
        });

        this.stacks.cleanDiscards();
        await this.stacks.deck.addCards(cards, undefined, { visible: false });
        
        this.getCurrentPlayerTable()?.setHandPoints(0, [0, 0, 0, 0]);
        this.updateTableHeight();
        this.stacks.deck.setCardNumber(args.remainingCardsInDeck, args.deckTopCard);

        return await this.stacks.deck.shuffle({ newTopCard: args.deckTopCard });
    }

    notif_updateCardsPoints(args: NotifUpdateCardsPointsArgs) {
        this.getCurrentPlayerTable()?.setHandPoints(args.cardsPoints, args.detailledPoints);
    }

    notif_betResult(args: NotifBetResultArgs) {
        this.getPlayerTable(args.playerId).showAnnouncementBetResult(args.result);
    }

    notif_emptyDeck() {
        this.playersTables.forEach(playerTable => playerTable.showEmptyDeck());
    }

    async notif_reshuffleDeck(args: NotifReshuffleDeckArgs) {
        return await this.stacks.deck.shuffle({ newTopCard: args.deckTopCard });
    }
    

    private clearLogs(activePlayer: string) {
        const logDivs = Array.from(document.getElementById('logs').getElementsByClassName('log')) as HTMLElement[];
        let hide = false;
        logDivs.forEach(logDiv => {
            if (!hide && logDiv.dataset.playerId == activePlayer) {
                hide = true;
            }
            if (hide) {
                logDiv.style.display = 'none';
                document.querySelector(`#chatwindowlogs_zone_tablelog_${(this as any).table_id} #docked${logDiv.id}`)?.classList.add('hidden-log-action');
            }
        });
    }

    private clearLogsInit(activePlayer: string) {
        if ((this as any).log_history_loading_status.downloaded && (this as any).log_history_loading_status.loaded >= (this as any).log_history_loading_status.total) {
            this.clearLogs(activePlayer);
        } else {
            setTimeout(() => this.clearLogsInit(activePlayer), 100);
        }
    }

    /* This enable to inject translatable styled things to logs or action bar */
    /* @Override */
    public format_string_recursive(log: string, args: any) {
        try {
            if (log && args && !args.processed) {
                if (args.announcement && args.announcement[0] != '<') {
                    args.announcement = `<strong style="color: darkred;">${_(args.announcement)}</strong>`;
                }
                if (args.call && args.call.length && args.call[0] != '<') {
                    args.call = `<strong class="title-bar-call">${_(args.call)}</strong>`;
                }

                ['discardNumber', 'roundPoints', 'cardsPoints', 'colorBonus', 'cardName', 'cardName1', 'cardName2', 'cardName3', 'cardColor', 'cardColor1', 'cardColor2', 'cardColor3', 'points', 'result'].forEach(field => {
                    if (args[field] !== null && args[field] !== undefined && args[field][0] != '<') {
                        args[field] = `<strong>${_(args[field])}</strong>`;
                    }
                })

            }
        } catch (e) {
            console.error(log,args,"Exception thrown", e.stack);
        }
        return (this as any).inherited(arguments);
    }
}