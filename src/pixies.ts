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
    private roundCounter: Counter;
    
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
            zoomLevels: [0.25, 0.375, 0.5, 0.625, 0.75, 0.875, 1, 1.25, 1.5, 1.75, 2],
            localStorageZoomKey: LOCAL_STORAGE_ZOOM_KEY,
        });
        
        this.roundCounter = new ebg.counter();
        this.roundCounter.create('round-counter');
        this.roundCounter.setValue(gamedatas.roundNumber);

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

        if (gamedatas.roundResult) {
            this.setRoundResult(gamedatas.roundResult);
        }

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
            case 'keepCard':
                this.onEnteringKeepCard(args.args);
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
        this.tableCenter.setSelectedCard(args.selectedCard);
        
        if ((this as any).isCurrentPlayerActive()) {
            this.getCurrentPlayerTable()?.setSelectableSpaces(args.spaces);
        }
    }

    private onEnteringKeepCard(args: EnteringKeepCardArgs) {
        this.tableCenter.setSelectedCard(args.selectedCard);
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
            case 'keepCard':
                this.onLeavingKeepCard();
                break;
        }
    }
    
    private onLeavingChooseCard() {      
        this.tableCenter.makeCardsSelectable(false);
    }

    private onLeavingPlayCard() {
        //this.tableCenter.removeSelectedCard();  
        this.getCurrentPlayerTable()?.setSelectableSpaces([]);
    }

    private onLeavingKeepCard() {
        //this.tableCenter.removeSelectedCard();  
    }

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    public onUpdateActionButtons(stateName: string, args: any) {
        if ((this as any).isCurrentPlayerActive()) {
            switch (stateName) {
                case 'playCard':
                    (this as any).addActionButton(`cancel_button`, _('Cancel'), () => this.cancel(), null, null, 'gray');
                    break;
                case 'keepCard':
                    const labels = [
                        _("Keep the card on the table"),
                        _("Keep the new card"),
                    ];
                    [0, 1].forEach(index => {
                        (this as any).addActionButton(`keepCard${index}_button`, `${labels[index]}<br><div id="keepCard${index}"></div>`, () => this.keepCard(index));
                        this.cardsManager.setForHelp(args.cards[index], `keepCard${index}`);
                    });
                    break;
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

    private setRoundResultForPlayer(playerId: number, detailledScore: DetailledScore) {
        if (!document.getElementById(`points-${playerId}`)) {
            const emptyRoundResult = [];
            Object.keys(this.gamedatas.players).forEach(id => emptyRoundResult[id] = null);
            this.setRoundResult(emptyRoundResult);
        }

        Object.entries(detailledScore).forEach(([key, value]) => document.getElementById(`${key}-${playerId}`).innerText = `${value}`);
    }

    private setRoundResult(roundResult: { [playerId: number]: DetailledScore }) {
        const playersIds = Object.keys(roundResult).map(Number);
        let html = `<table class='round-result'>
            <tr><th class="empty"></th>${playersIds.map(playerId => `<th class="name"><strong style='color: #${this.getPlayer(playerId).color};'>${this.getPlayer(playerId).name}</strong></th>`).join('')}</tr>
            <tr><th class="type"><div class="score-icon validated"></div></th>${playersIds.map(playerId => `<td id="validatedCardPoints-${playerId}">${roundResult[playerId]?.validatedCardPoints ?? ''}</td>`).join('')}</tr>
            <tr><th class="type"><div class="score-icon zone"></div></th>${playersIds.map(playerId => `<td id="largestColorZonePoints-${playerId}">${roundResult[playerId]?.largestColorZonePoints ?? ''}</td>`).join('')}</tr>
            <tr><th class="type"><div class="score-icon spirals"></div></th>${playersIds.map(playerId => `<td id="spiralsAndCrossesPoints-${playerId}">${roundResult[playerId]?.spiralsAndCrossesPoints ?? ''}</td>`).join('')}</tr>
            <tr><th class="type"><div class="score-icon sum"></div></th>${playersIds.map(playerId => `<th class="sum" id="points-${playerId}">${roundResult[playerId]?.points ?? ''}</th>`).join('')}</tr>
        </table>`;

        document.getElementById(`result`).innerHTML = html;
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

    public seen() {
        if(!(this as any).checkAction('seen')) {
            return;
        }

        this.takeAction('seen');
    }

    public cancel() {
        if(!(this as any).checkAction('cancel')) {
            return;
        }

        this.takeAction('cancel');
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
            ['newRound', ANIMATION_MS],
            ['newTurn', undefined],
            ['playCard', undefined],
            ['keepCard', undefined],
            ['endRound', undefined],
            ['score', ANIMATION_MS * 3],
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

    notif_newRound(args: NotifNewRoundArgs) {
        document.getElementById(`result`).innerHTML = ``;
        const { round } = args;
        this.roundCounter.toValue(round);
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
        const { playerId, newScore, detailledScore } = args;
        (this as any).scoreCtrl[playerId]?.toValue(newScore);

        (this as any).displayScoring(`player-table-${playerId}-cards`, this.getPlayerColor(playerId), detailledScore.points, ANIMATION_MS * 3);
        
        this.setRoundResultForPlayer(playerId, detailledScore);
    }

    async notif_endRound(args: NotifEndRoundArgs) {
        const cards = this.tableCenter.getTableCards();

        this.playersTables.forEach(playerTable => cards.push(...playerTable.getAllCards()));

        await this.tableCenter.deck.addCards(cards, undefined, { visible: false });
        this.tableCenter.deck.setCardNumber(args.remainingCardsInDeck);

        return await this.tableCenter.deck.shuffle();
    }

    /* This enable to inject translatable styled things to logs or action bar */
    /* @Override */
    public format_string_recursive(log: string, args: any) {
        try {
            if (log && args && !args.processed) {
                ['roundNumber', 'value', 'incScore'].forEach(field => {
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