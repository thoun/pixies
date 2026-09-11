<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\Pixies\Game;
use Bga\Games\Pixies\Objects\Card;

class KeepCard extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id: \ST_PLAYER_KEEP_CARD,
            type: StateType::ACTIVE_PLAYER,
            name: 'keepCard',
            description: clienttranslate('${actplayer} must choose a card to keep'),
            descriptionMyTurn: clienttranslate('${you} must choose a card to keep'),
            transitions: [
                'next' => NextPlayer::class,
                'cancel' => ChooseCard::class,
                'zombiePass' => NextPlayer::class,
            ],
        );
    }

    public function getArgs(int $activePlayerId): array
    {
        $card = $this->game->getSelectedCard();
        $spaceCards = $this->game->getCardsFromSpace($activePlayerId, $card->value);

        return [
            'selectedCard' => $card,
            'cards' => [$spaceCards[0], $card],
        ];
    }

    #[PossibleAction]
    public function actChooseCard(int $id, bool $autoplace): void
    {
        $this->game->actChooseCard($id, $autoplace);
    }

    #[PossibleAction]
    public function actKeepCard(int $index, int $activePlayerId): void
    {
        if (!in_array($index, [0, 1])) {
            throw new \BgaUserException('Invalid index');
        }

        $this->applyKeepCard($activePlayerId, $index);
    }

    #[PossibleAction]
    public function actCancel()
    {
        return ChooseCard::class;
    }

    public function zombie(int $playerId): void
    {
        $roundNumber = intval($this->game->getStat('roundNumber'));
        $isFlowerPowerExpansion = $this->game->isFlowerPowerExpansion();
        $playerCards = $this->game->getCardsFromSpaces($playerId);
        $card = $this->game->getSelectedCard();

        $possibleAnswerPoints = array_map(
            fn($choice) => self::getPointsFromZombieChoice(
                $this->game,
                $playerCards,
                $roundNumber,
                $isFlowerPowerExpansion,
                $card,
                $choice,
            ),
            [0, 1],
        );

        $maxPoints = max($possibleAnswerPoints);
        $maxPointsAnswers = array_keys($possibleAnswerPoints, $maxPoints);
        $zombieChoice = $maxPointsAnswers[bga_rand(0, count($maxPointsAnswers) - 1)];

        $this->applyKeepCard($playerId, $zombieChoice);
    }

    public static function getPointsFromZombieChoice(
        Game $game,
        array $playerCards,
        int $roundNumber,
        bool $isFlowerPowerExpansion,
        $card,
        int $choice,
    ): int {
        $space = $card->value;
        $playerCards[$space] = $choice === 0
            ? [Card::onlyId($card), $playerCards[$space][0]]
            : [Card::onlyId($playerCards[$space][0]), $card];

        return $game->getDetailledScore($playerCards, $roundNumber, $isFlowerPowerExpansion)->points;
    }

    public function applyKeepCard(int $playerId, int $index) {
        $card = $this->game->getSelectedCard();
        $space = $card->value;
        $spaceCard = $this->game->getCardsFromSpace($playerId, $space)[0];

        $hiddenCard = $index == 0 ? $card : $spaceCard;
        $visibleCard = $index == 1 ? $card : $spaceCard;

        $hiddenCard->locationArg = 0;
        $visibleCard->locationArg = 1;

        $this->game->cards->moveCard($hiddenCard->id, "player-$playerId-$space", $hiddenCard->locationArg);
        $this->game->cards->moveCard($visibleCard->id, "player-$playerId-$space", $visibleCard->locationArg);

        $this->notify->all('keepCard', clienttranslate('${player_name} keeps the ${color} card on space ${value}'), [
            'playerId' => $playerId,
            'hiddenCard' => Card::onlyId($hiddenCard),
            'visibleCard' => $visibleCard,
            'space' => $space,
        ]);
        
        $this->bga->playerStats->inc('validatedCard', 1, $playerId, updateTableStat: true);

        $this->gamestate->nextState('next');
    }
}
