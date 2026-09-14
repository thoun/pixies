<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\Pixies\Game;
use Bga\Games\Pixies\Objects\Card;
use Bga\GameFramework\Helpers\Collection;
use Bga\GameFramework\UserException;

class ChooseCard extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id: \ST_PLAYER_CHOOSE_CARD,
            type: StateType::ACTIVE_PLAYER,
            name: 'chooseCard',
            description: clienttranslate('${actplayer} must choose a card to play'),
            descriptionMyTurn: clienttranslate('${you} must choose a card to play'),
            transitions: [
                'playCard' => PlayCard::class,
                'keepCard' => KeepCard::class,
            ],
        );
    }

    public function getArgs(): array
    {
        return [];
    }

    #[PossibleAction]
    public function actChooseCard(int $id, int $activePlayerId)
    {
        $card = $this->game->cardManager->getTableCards()->find(fn($c) => $c->id === $id);
        if (!$card) {
            throw new UserException("You cannot choose this card");
        }

        return $this->game->applyChooseCard($activePlayerId, $card);
    }

    public function zombie(int $playerId)
    {
        $roundNumber = $this->bga->tableStats->get('roundNumber');
        $isFlowerPowerExpansion = $this->game->isFlowerPowerExpansion();
        $playerCards = $this->game->cardManager->getCardsFromSpaces($playerId);
        $tableCards = $this->game->cardManager->getTableCards();

        $possibleAnswerPoints = [];
        foreach ($tableCards as $choice => $card) {
            $possibleAnswerPoints[$choice] = $this->getPointsFromChoice(
                $playerCards,
                $roundNumber,
                $isFlowerPowerExpansion,
                $card,
            );
        }

        $maxPoints = max($possibleAnswerPoints);
        $maxPointsAnswers = array_keys($possibleAnswerPoints, $maxPoints);
        $zombieChoice = $maxPointsAnswers[bga_rand(0, count($maxPointsAnswers) - 1)];

        return $this->game->applyChooseCard($playerId, $tableCards[$zombieChoice]);
    }

    /**
     * @param array<string,Card[]> $playerCards
     */    
    private function getPointsFromChoice(
        array $playerCards,
        int $roundNumber,
        bool $isFlowerPowerExpansion,
        Card $card,
    ): int {
        [$row, $column] = Game::getRowColumnFromValue($card->value);
        $coordinate = $row.'-'.$column;
        if (count($playerCards[$coordinate]) === 1 && $playerCards[$coordinate][0]->value === $card->value) {
            $possibleAnswerPoints = array_map(
                fn($choice) => KeepCard::getPointsFromZombieChoice(
                    $this->game,
                    $playerCards,
                    $roundNumber,
                    $isFlowerPowerExpansion,
                    $card,
                    $choice,
                ),
                [0, 1],
            );

            return max($possibleAnswerPoints);
        }

        $possibleSpaces = $this->game->cardManager->getPossibleSpacesForCard($playerCards, $card);
        $possibleAnswerPoints = [];
        foreach ($possibleSpaces as $choice) {
            $choiceSplit = explode('-', $choice);
            $row = intval($choiceSplit[0]);
            $column = intval($choiceSplit[1]);
            $possibleAnswerPoints[$choice] = PlayCard::getPointsFromZombieChoice(
                $this->game,
                $playerCards,
                $roundNumber,
                $isFlowerPowerExpansion,
                $card,
                $row,
                $column,
            );
        }

        return max($possibleAnswerPoints);
    }
}
