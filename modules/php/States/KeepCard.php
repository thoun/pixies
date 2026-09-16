<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\GameFramework\UserException;
use Bga\Games\Pixies\Game;
use Bga\Games\Pixies\Objects\Card;
use Bga\GameFramework\Helpers\Collection;

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
        );
    }

    public function getArgs(int $activePlayerId): array
    {
        $card = $this->game->cardManager->getSelectedCard();
        [$row, $column] = Game::getRowColumnFromValue($card->value);
        $spaceCards = $this->game->cardManager->getCardsFromSpaces($activePlayerId)[$row.'-'.$column];

        return [
            'selectedCard' => $card,
            'cards' => [$spaceCards[0], $card],
        ];
    }

    #[PossibleAction]
    public function actChooseCard(int $id, int $activePlayerId): void
    {
        $this->game->actChooseCard($id, $activePlayerId);
    }

    #[PossibleAction]
    public function actKeepCard(int $index, int $activePlayerId): void
    {
        if (!in_array($index, [0, 1])) {
            throw new UserException('Invalid index');
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
        $roundNumber = $this->bga->tableStats->get('roundNumber');
        $isFlowerPowerExpansion = $this->game->isFlowerPowerExpansion();
        $playerCards = $this->game->cardManager->getPlayerCards($playerId);
        $card = $this->game->cardManager->getSelectedCard();

        $possibleAnswerPoints = array_map(
            fn($choice) => $this->game->cardManager->getPointsFromZombieKeepCardChoice(
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

    public function applyKeepCard(int $playerId, int $index) {
        $card = $this->game->cardManager->getSelectedCard();
        $space = $card->value;
        [$row, $column] = Game::getRowColumnFromValue($space);
        $spaceCard = $this->game->cardManager->getCardsFromSpaces($playerId)[$row.'-'.$column][0];

        $hiddenCard = $index == 0 ? $card : $spaceCard;
        $visibleCard = $index == 1 ? $card : $spaceCard;
        $this->game->cardManager->keepCard($playerId, $hiddenCard, $visibleCard, $row, $column);

        $this->notify->all('keepCard', clienttranslate('${player_name} keeps the ${color} card on space ${value}'), [
            'playerId' => $playerId,
            'hiddenCard' => Card::onlyId($hiddenCard),
            'visibleCard' => $visibleCard,
            'space' => $space,
            'row' => $spaceCard->row,
            'column' => $spaceCard->column,
        ]);
        
        $this->bga->playerStats->inc('validatedCard', 1, $playerId, updateTableStat: true);

        $this->gamestate->jumpToState(NextPlayer::class);
    }
}
