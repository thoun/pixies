declare const define;
declare const ebg;
declare const $;
declare const dojo: Dojo;
declare const _;
declare const g_gamethemeurl;
declare const bgaConfig;

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

        (this as any).getGameAreaElement().insertAdjacentHTML('beforeend', `
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

        if (gamedatas.lastTurn) {
            this.notif_lastTurn(false);
        }

        this.setupNotifications();
        this.setupPreferences();
        new HelpManager(this, { 
            buttons: [
                new BgaHelpPopinButton({
                    title: _("Scoring"),
                    html: this.getHelpHtml(),
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

        if (gamedatas.roundResult[gamedatas.roundNumber]) {
            this.setRoundResult(gamedatas.roundResult[gamedatas.roundNumber], gamedatas.roundNumber);
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
                    (this as any).statusBar.addActionButton(_('Cancel'), () => (this as any).bgaPerformAction('actCancel'), { color: 'secondary' });
                    break;
                case 'keepCard':
                    const labels = [
                        _("Keep the card on the table"),
                        _("Keep the new card"),
                    ];
                    [0, 1].forEach(index => {
                        (this as any).statusBar.addActionButton(`${labels[index]}<br><div id="keepCard${index}"></div>`, () => (this as any).bgaPerformAction('actKeepCard', { index }), { id: `keepCard${index}_button` });
                        this.cardsManager.setForHelp(args.cards[index], `keepCard${index}`);
                    });
                    (this as any).statusBar.addActionButton(_('Cancel'), () => (this as any).bgaPerformAction('actCancel'), { color: 'secondary' });
                    break;
                case 'beforeEndRound':
                    (this as any).statusBar.addActionButton(_("Seen"), () => (this as any).bgaPerformAction('actSeen'));
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
        (this as any).bgaPerformAction('actPlayCard', { space });
    }

    private getHelpHtml() {
        
        let html = `
        <div id="help-popin">
            <h1>${_("Validated cards")}</h1>
            ${_("Each validated card earns as many points as the number on it.")}

            <h1>${_("Symbols")}</h1>
            ${_("A spiral earns 1 point.")}<br>
            ${_("A cross makes the player lose 1 point.")}<br>
            ${_("Spiral")} / <div class="color-icon" data-row="0"></div> : ${_("1 spiral for each faceup card of the indicated color.")}<br><br>            
            ${_("<strong>Note:</strong> All faceup cards are taken into account, whether they are validated or not.")}

            <h1>${_("The player’s largest color zone")}</h1>
            ${_("A color zone is made up of at least 2 cards of the same color touching along a side. Diagonals do not count. Each card that is part of the player’s largest zone earns:")}
            <ul>
            ${[1, 2, 3].map(roundNumber => `<li>${_("${points} points in round ${round}")}</li>`.replace('${points}', `${roundNumber + 1}`).replace('${round}', `${roundNumber}`)).join('')}
            </ul>
            ${_("<strong>Note:</strong> All faceup cards are taken into account, whether they are validated or not.")}
            <br><br>
            ${_("A multi-colored card has all the colors at the same time. This means that it counts for the player’s color zone of course, but also for all their special cards as well.")}
        </div>
        `;
        
        return html;
    }

    private getColorAddHtml() {
        return [1, 2, 3, 4].map((number, index) => `<div class="color-icon" data-row="${index}"></div><span class="label"> ${this.cardsManager.getColor(number)}</span>`).join('');
    }

    private setRoundResultForPlayer(playerId: number, detailledScore: DetailledScore, round: number) {
        if (!document.getElementById(`points-${round}-${playerId}`)) {
            const emptyRoundResult = [];
            Object.keys(this.gamedatas.players).forEach(id => emptyRoundResult[id] = null);
            this.setRoundResult(emptyRoundResult, round);
        }

        Object.entries(detailledScore).forEach(([key, value]) => document.getElementById(`${key}-${round}-${playerId}`).innerText = `${value}`);
    }

    private setRoundResult(roundResult: { [playerId: number]: DetailledScore }, round: number) {
        if (this.gamedatas.roundResult[round - 1]) {
            this.setRoundResult(this.gamedatas.roundResult[round - 1], round - 1);
        }

        const playersIds = Object.keys(roundResult).map(Number);
        let html = `<table class='round-result'>
            <tr><th class="empty"></th><th colspan="${playersIds.length}" class="round">${_("Round")} <strong>${round}</strong></th></tr>
            <tr><th class="empty"></th>${playersIds.map(playerId => `<th class="name"><strong style='color: #${this.getPlayer(playerId).color};'>${this.getPlayer(playerId).name}</strong></th>`).join('')}</tr>
            <tr><th class="type"><div class="score-icon validated"></div></th>${playersIds.map(playerId => `<td id="validatedCardPoints-${round}-${playerId}">${roundResult[playerId]?.validatedCardPoints ?? ''}</td>`).join('')}</tr>
            <tr><th class="type"><div class="score-icon zone" data-round="${round}"></div></th>${playersIds.map(playerId => `<td id="largestColorZonePoints-${round}-${playerId}">${roundResult[playerId]?.largestColorZonePoints ?? ''}</td>`).join('')}</tr>
            <tr><th class="type"><div class="score-icon spirals"></div></th>${playersIds.map(playerId => `<td id="spiralsAndCrossesPoints-${round}-${playerId}">${roundResult[playerId]?.spiralsAndCrossesPoints ?? ''}</td>`).join('')}</tr>
            <tr><th class="type"><div class="score-icon sum"></div></th>${playersIds.map(playerId => `<th class="sum" id="points-${round}-${playerId}">${roundResult[playerId]?.points ?? ''}</th>`).join('')}</tr>
        </table>`;

        document.getElementById(`result`).insertAdjacentHTML('beforeend', html);
    }

    public chooseCard(id: number) {
        (this as any).bgaPerformAction('actChooseCard', {
            id,
            autoplace: (this as any).getGameUserPreference(201) === 1
        });
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

        (this as any).bgaSetupPromiseNotifications();
    }

    async notif_newRound(args: NotifNewRoundArgs) {
        document.getElementById(`result`).innerHTML = ``;
        document.getElementById(`last-round`)?.remove();
        const { round } = args;
        this.roundCounter.toValue(round);

        await (this as any).wait(ANIMATION_MS);
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
    
    /** 
     * Show last turn banner.
     */ 
    async notif_lastTurn(animate: boolean = true) {
        dojo.place(`<div id="last-round">
            <span class="last-round-text ${animate ? 'animate' : ''}">${_("This is the final turn of the round!")}</span>
        </div>`, 'page-title');
    }

    async notif_score(args: NotifScoreArgs) {
        document.getElementById(`last-round`)?.remove();
        const { playerId, newScore, detailledScore, round } = args;
        (this as any).scoreCtrl[playerId]?.toValue(newScore);

        (this as any).displayScoring(`player-table-${playerId}-cards`, this.getPlayerColor(playerId), detailledScore.points, ANIMATION_MS * 3);
        
        this.setRoundResultForPlayer(playerId, detailledScore, round);

        await (this as any).wait(ANIMATION_MS * 3);
    }

    async notif_roundResult(args: NotifRoundResultArgs) {
        document.getElementById(`last-round`)?.remove();
        this.gamedatas.roundResult[args.round] = args.roundResult;
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