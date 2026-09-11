<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\Pixies\Game;

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
                'zombiePass' => NextPlayer::class,
            ],
        );
    }

    public function getArgs(): array
    {
        return [];
    }

    #[PossibleAction]
    public function actChooseCard(int $id, bool $autoplace): void
    {
        $this->game->actChooseCard($id, $autoplace);
    }

    public function zombie(int $playerId)
    {
        $roundNumber = $this->bga->tableStats->get('roundNumber');
        $isFlowerPowerExpansion = $this->game->isFlowerPowerExpansion();
        $playerCards = $this->game->getCardsFromSpaces($playerId);
        $tableCards = $this->game->getCardsFromDb($this->game->cards->getCardsInLocation('table'));

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

    private function getPointsFromChoice(
        array $playerCards,
        int $roundNumber,
        bool $isFlowerPowerExpansion,
        $card,
    ): int {
        if (count($playerCards[$card->value]) === 1 && $playerCards[$card->value][0]->value === $card->value) {
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

        $possibleSpaces = $this->game->getPossibleSpacesForCard($playerCards, $card);
        $possibleAnswerPoints = [];
        foreach ($possibleSpaces as $choice) {
            $possibleAnswerPoints[$choice] = PlayCard::getPointsFromZombieChoice(
                $this->game,
                $playerCards,
                $roundNumber,
                $isFlowerPowerExpansion,
                $card,
                $choice,
            );
        }

        return max($possibleAnswerPoints);
    }
}
