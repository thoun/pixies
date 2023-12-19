declare const define;
declare const ebg;
declare const $;
declare const dojo: Dojo;
declare const _;
declare const g_gamethemeurl;

const ANIMATION_MS = 500;
const ACTION_TIMER_DURATION = 5;

const LOCAL_STORAGE_ZOOM_KEY = 'Pixies-zoom';

class Pixies implements PixiesGame {
    public animationManager: AnimationManager;
    public cardsManager: CardsManager;

    private zoomManager: ZoomManager;
    private gamedatas: PixiesGamedatas;
    private tableCenter: TableCenter;
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
        
        this.gamedatas = gamedatas;

        log('gamedatas', gamedatas);

        this.animationManager = new AnimationManager(this);
        this.cardsManager = new CardsManager(this);
        this.tableCenter = new TableCenter(this, this.gamedatas);
        this.createPlayerTables(gamedatas);
        
        this.zoomManager = new ZoomManager({
            element: document.getElementById('full-table'),
            smooth: false,
            zoomControls: {
                color: 'white',
            },
            localStorageZoomKey: LOCAL_STORAGE_ZOOM_KEY,
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
            case 'chooseCard':
                this.onEnteringChooseCard(args.args);
                break;
            case 'playCard':
                this.onEnteringPlayCard(args.args);
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
    
    private onEnteringChooseCard(args: EnteringChooseCardArgs) {
        if ((this as any).isCurrentPlayerActive()) {
            this.tableCenter.makeCardsSelectable(true);
        }
    }

    private onEnteringPlayCard(args: EnteringPlayCardArgs) {
        if ((this as any).isCurrentPlayerActive()) {
            this.getCurrentPlayerTable()?.setSelectableSpaces(args.spaces);
        }
    }
    
    private onEnteringChooseDiscardPile() {
        this.tableCenter.makeCardsSelectable((this as any).isCurrentPlayerActive());
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
            this.discardStock.addCard(card, { fromStock: this.tableCenter.getDiscardDeck(args.discardNumber) });
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
            case 'chooseCard':
                this.onLeavingChooseCard();
                break;
            case 'playCard':
                this.onLeavingPlayCard();
                break;
            case 'chooseDiscardCard':
                this.onLeavingChooseDiscardCard();
                break;
            case 'chooseOpponent':
                this.onLeavingChooseOpponent();
                break;
        }
    }
    
    private onLeavingChooseCard() {
        this.tableCenter.makeCardsSelectable(false);
    }

    private onLeavingPlayCard() {
        this.getCurrentPlayerTable()?.setSelectableSpaces([]);
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
                case 'playCard':
                    const playCardArgs = args as EnteringPlayCardArgs;
                    (this as any).addActionButton(`playCard_button`, _("Play selected cards"), () => this.playSelectedCards());
                    (this as any).addActionButton(`endTurn_button`, _("End turn"), () => this.endTurn());
                    if (playCardArgs.canCallEndRound) {
                        (this as any).addActionButton(`endRound_button`, _('End round') + ' ("' + _('LAST CHANCE') + '")', () => this.endRound(), null, null, 'red');

                        this.setTooltip(`endRound_button`, `${_("Say <strong>LAST CHANCE</strong> if you are willing to take the bet of having the most points at the end of the round. The other players each take a final turn (take a card + play cards) which they complete by revealing their hand, which is now protected from attacks. Then, all players count the points on their cards (in their hand and in front of them).")}<br><br>
                        ${_("If your hand is higher or equal to that of your opponents, bet won! You score the points for your cards + the color bonus (1 point per card of the color they have the most of). Your opponents only score their color bonus.")}<br><br>
                        ${_("If your score is less than that of at least one opponent, bet lost! You score only the color bonus. Your opponents score points for their cards.")}`);
                    }
                    dojo.addClass(`playCard_button`, `disabled`);
                    /*if (!playCardArgs.canCallEndRound) {
                        dojo.addClass(`endRound_button`, `disabled`);
                    }*/
                    
                    if (!playCardArgs.canDoAction) {
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
    
    public onTableCardClick(card: Card): void {
        this.chooseCard(card.id);
    }

    public onSpaceClick(space: number): void {
        this.playCard(space);
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

        const duoCardsNumbers = [1, 2, 3, 4, 5];
        const multiplierNumbers = [1, 2, 3, 4];

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
        
        html += `
        </div>
        `;
        
        // Show the dialog
        helpDialog.setContent(html);

        helpDialog.show();

        // pair
        const duoCardsPairs = [[1, 1], [2, 2], [3, 3], [4, 4], [5, 5]];
        duoCardsPairs.forEach(([family, color]) => this.cardsManager.setForHelp({id: 1020 + family, category: 2, family, color, index: 0 } as any, `help-pair-${family}`));
        // mermaid
        this.cardsManager.setForHelp({id: 1010, category: 1 } as any, `help-mermaid`);
        // collector
        [[1, 1], [2, 2], [3, 6], [4, 9]].forEach(([family, color]) => this.cardsManager.setForHelp({id: 1030 + family, category: 3, family, color, index: 0 } as any, `help-collector-${family}`));
        // multiplier
        multiplierNumbers.forEach(family => this.cardsManager.setForHelp({id: 1040 + family, category: 4, family } as any, `help-multiplier-${family}`));
    }

    public chooseCard(id: number) {
        if(!(this as any).checkAction('chooseCard')) {
            return;
        }

        this.takeAction('chooseCard', {
            id
        });
    }

    public playCard(space: number) {
        if(!(this as any).checkAction('playCard')) {
            return;
        }

        this.takeAction('playCard', {
            space
        });
    }

    public playCardTrio(ids: number[], starfishId: number) {
        if(!(this as any).checkAction('playCardTrio')) {
            return;
        }

        this.takeAction('playCardTrio', {
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

    public endRound() {
        if(!(this as any).checkAction('endRound')) {
            return;
        }

        this.takeAction('endRound');
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

        const notifs = [
            ['cardInDiscardFromDeck', ANIMATION_MS],
            ['cardInHandFromDiscard', ANIMATION_MS],
            ['cardInHandFromDiscardCrab', ANIMATION_MS],
            ['cardInHandFromPick', ANIMATION_MS],
            ['cardInHandFromDeck', ANIMATION_MS],
            ['cardInDiscardFromPick', ANIMATION_MS],
            ['cardsInDeckFromPick', ANIMATION_MS],
            ['playCard', undefined],
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

    notif_cardInDiscardFromDeck(args: NotifCardInDiscardFromDeckArgs) {
        this.tableCenter.setDiscardCard(args.discardId, args.card, 1, document.getElementById('deck'));
        this.tableCenter.deck.setCardNumber(args.remainingCardsInDeck, args.deckTopCard);
        this.updateTableHeight();
    } 

    notif_cardInHandFromDiscard(args: NotifCardInHandFromDiscardArgs) {
        const card = args.card;
        const playerId = args.playerId;
        const discardNumber = args.discardId;
        const maskedCard = playerId == this.getPlayerId() ? card : { id: card.id } as Card;
        this.getPlayerTable(playerId).addCardsToHand([maskedCard]);
        this.handCounters[playerId].incValue(1);

        this.tableCenter.setDiscardCard(discardNumber, args.newDiscardTopCard, args.remainingCardsInDiscard);
        this.updateTableHeight();
    } 

    notif_cardInHandFromDiscardCrab(args: NotifCardInHandFromDiscardArgs) {
        const card = args.card;
        const playerId = args.playerId;
        const maskedCard = playerId == this.getPlayerId() ? card : { id: card.id } as Card;
        this.getPlayerTable(playerId).addCardsToHand([maskedCard]);
        this.handCounters[playerId].incValue(1);

        this.tableCenter.setDiscardCard(args.discardId, args.newDiscardTopCard, args.remainingCardsInDiscard);
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
        this.tableCenter.deck.setCardNumber(args.remainingCardsInDeck, args.deckTopCard)
        this.handCounters[playerId].incValue(1);
    }   

    notif_cardInDiscardFromPick(args: NotifCardInDiscardFromPickArgs) {
        const card = args.card;
        const discardNumber = args.discardId;
        this.cardsManager.setCardVisible(card, true);
        this.tableCenter.setDiscardCard(discardNumber, card, args.remainingCardsInDiscard);
        this.updateTableHeight();
    } 

    notif_cardsInDeckFromPick(args: NotifCardsInDeckFromPickArgs) {
        this.tableCenter.deck.addCards(args.cards, undefined, {
            visible: false,
        });
        this.tableCenter.deck.setCardNumber(args.remainingCardsInDeck, args.deckTopCard);
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

    notif_playCard(args: NotifPlayCardArgs) {
        /*const playerId = args.playerId;
        const cards = args.cards;
        const playerTable = this.getPlayerTable(playerId);
        this.handCounters[playerId].incValue(-cards.length);
        return playerTable.addCardsToTable(cards);*/
    }

    notif_revealHand(args: NotifRevealHandArgs) {
        const playerId = args.playerId;
        const playerPoints = args.playerPoints;
        const playerTable = this.getPlayerTable(playerId);
        playerTable.showAnnouncementPoints(playerPoints);

        this.notif_playCard(args);
        this.handCounters[playerId].toValue(0);
    }

    notif_announceEndRound(args: NotifAnnounceEndRoundArgs) {
        this.getPlayerTable(args.playerId).showAnnouncement(args.announcement);
    }

    async notif_endRound(args: NotifEndRoundArgs) {
        const cards = this.tableCenter.getDiscardCards();

        this.playersTables.forEach(playerTable => {
            cards.push(...playerTable.getAllCards());
            this.handCounters[playerTable.playerId].setValue(0);
            playerTable.clearAnnouncement();
        });

        this.tableCenter.cleanDiscards();
        await this.tableCenter.deck.addCards(cards, undefined, { visible: false });
        
        this.getCurrentPlayerTable()?.setHandPoints(0, [0, 0, 0, 0]);
        this.updateTableHeight();
        this.tableCenter.deck.setCardNumber(args.remainingCardsInDeck, args.deckTopCard);

        return await this.tableCenter.deck.shuffle({ newTopCard: args.deckTopCard });
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
        return await this.tableCenter.deck.shuffle({ newTopCard: args.deckTopCard });
    }

    /* This enable to inject translatable styled things to logs or action bar */
    /* @Override */
    public format_string_recursive(log: string, args: any) {
        try {
            if (log && args && !args.processed) {
                if (args.announcement && args.announcement[0] != '<') {
                    args.announcement = `<strong style="color: darkred;">${_(args.announcement)}</strong>`;
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