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
        new HelpManager(this, { 
            buttons: [
                new BgaHelpPopinButton({
                    title: _("Card help").toUpperCase(),
                    html: this.getHelpHtml(),
                    onPopinCreated: () => this.getHelpHtml(),
                    buttonBackground: '#3d5c28',
                }),
                new BgaHelpExpandableButton({
                    unfoldedHtml: this.getColorAddHtml(),
                    foldedContentExtraClasses: 'color-help-folded-content',
                    unfoldedContentExtraClasses: 'color-help-unfolded-content',
                    expandedWidth: '120px',
                    expandedHeight: '168px',
                }),
            ]
        });

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
                case 'keepCard':
                    (this as any).addActionButton(`keepCard0_button`, _("Keep the card on the table"), () => this.keepCard(0));
                    (this as any).addActionButton(`keepCard1_button`, _("Keep the new card"), () => this.keepCard(1));
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

    private getHelpHtml() {
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
        
        return html;
    }

    private getColorAddHtml() {
        return [1, 2, 3, 4].map((number, index) => `<div class="color-icon" data-row="${index}"></div><span class="label"> ${this.cardsManager.getColor(number)}</span>`).join('');
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

    public keepCard(index: number) {
        if(!(this as any).checkAction('keepCard')) {
            return;
        }

        this.takeAction('keepCard', {
            index
        });
    }

    public takeAction(action: string, data?: any) {
        data = data || {};
        data.lock = true;
        (this as any).ajaxcall(`/pixies/pixies/${action}.html`, data, this, () => {});
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
            ['newTurn', undefined],
            ['playCard', undefined],
            ['keepCard', undefined],
            ['revealHand', ANIMATION_MS * 2],
            ['announceEndRound', ANIMATION_MS * 2],
            ['betResult', ANIMATION_MS * 2],
            ['endRound', undefined],
            ['score', ANIMATION_MS * 3],
            ['newRound', ANIMATION_MS * 3],
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
    }

    async notif_newTurn(args: NotifNewTurnArgs) {
        const { cards } = args;
        await this.tableCenter.newTurn(cards);
    }

    async notif_playCard(args: NotifPlayCardArgs) {
        const { playerId, card, space } = args;
        const playerTable = this.getPlayerTable(playerId);
        await playerTable.playCard(card, space);
    }

    async notif_keepCard(args: NotifKeepCardArgs) {
        const { playerId, hiddenCard, visibleCard, space } = args;
        const playerTable = this.getPlayerTable(playerId);
        await playerTable.keepCard(hiddenCard, visibleCard, space);
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