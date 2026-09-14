<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\Pixies\Game;

class PlayCard extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id: \ST_PLAYER_PLAY_CARD,
            type: StateType::ACTIVE_PLAYER,
            name: 'playCard',
            description: clienttranslate('${actplayer} must place the card'),
            descriptionMyTurn: clienttranslate('${you} must place the card'),
            transitions: [
                'next' => NextPlayer::class,
                'cancel' => ChooseCard::class,
                'zombiePass' => NextPlayer::class,
            ],
        );
    }

    public function getArgs(): array
    {
        return $this->game->argPlayCard();
    }

    #[PossibleAction]
    public function actChooseCard(int $id, bool $autoplace): void
    {
        $this->game->actChooseCard($id, $autoplace);
    }

    #[PossibleAction]
    public function actPlayCard(int $space, int $activePlayerId, array $args): void
    {
        if (!in_array($space, $args['spaces'])) {
            throw new \BgaUserException('Invalid space');
        }

        $this->game->applyPlayCard($activePlayerId, $space);
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
        $playerCards = $this->game->getCardsFromSpaces($playerId);
        $card = $this->game->getSelectedCard();
        $possibleSpaces = $this->game->getPossibleSpacesForCard($playerCards, $card);

        $possibleAnswerPoints = [];
        foreach ($possibleSpaces as $choice) {
            $possibleAnswerPoints[$choice] = self::getPointsFromZombieChoice(
                $this->game,
                $playerCards,
                $roundNumber,
                $isFlowerPowerExpansion,
                $card,
                $choice,
            );
        }

        $maxPoints = max($possibleAnswerPoints);
        $maxPointsAnswers = array_keys($possibleAnswerPoints, $maxPoints);
        $zombieChoice = $maxPointsAnswers[bga_rand(0, count($maxPointsAnswers) - 1)];

        $this->game->applyPlayCard($playerId, $zombieChoice);
    }

    public static function getPointsFromZombieChoice(
        Game $game,
        array $playerCards,
        int $roundNumber,
        bool $isFlowerPowerExpansion,
        $card,
        int $choice,
    ): int {
        $playerCards[$choice] = array_merge($playerCards[$choice], [$card]);

        return $game->getDetailledScore($playerCards, $roundNumber, $isFlowerPowerExpansion)->points;
    }
}
