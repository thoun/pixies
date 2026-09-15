<?php

declare(strict_types=1);

namespace Bga\Games\Pixies\States;

use Bga\GameFramework\Helpers\Collection;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\GameFramework\UserException;
use Bga\Games\Pixies\Game;
use Bga\Games\Pixies\Objects\Card;

use function Bga\Games\Pixies\debug;

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
        );
    }

    public function getArgs(int $activePlayerId): array
    {
        $card = $this->game->cardManager->getSelectedCard();
        $playerCards = $this->game->cardManager->getCardsFromSpaces($activePlayerId);

        $spaces = $this->game->cardManager->getPossibleSpacesForCard($playerCards, $card);
    
        return [
            'selectedCard' => $card,
            'spaces' => $spaces,
        ];
    }

    public function onEnteringState(int $activePlayerId) {
        if ($this->bga->userPreferences->get($activePlayerId, 201) !== 1) {
            return; // no autoplace
        }
        $args = $this->getArgs($activePlayerId);
        if (count($args['spaces']) !== 1) {
            return; // more than one space available
        }
        $coordinate = array_map(fn($n) => intval($n), explode('-', $args['spaces'][0]));
        return $this->actPlayCard($coordinate[0], $coordinate[1], $activePlayerId, $args);
    }

    #[PossibleAction]
    public function actChooseCard(int $id, int $activePlayerId): void
    {
        $this->game->actChooseCard($id, $activePlayerId);
    }

    #[PossibleAction]
    public function actPlayCard(int $row, int $column, int $activePlayerId, array $args): void
    {
        if (!in_array($row.'-'.$column, $args['spaces'])) {
            throw new UserException('Invalid space');
        }

        $this->applyPlayCard($activePlayerId, $row, $column);
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
        $playerCards = $this->game->cardManager->getCardsFromSpaces($playerId);
        $card = $this->game->cardManager->getSelectedCard();
        $possibleSpaces = $this->game->cardManager->getPossibleSpacesForCard($playerCards, $card);

        $possibleAnswerPoints = [];
        foreach ($possibleSpaces as $choice) {
            $choiceSplit = explode('-', $choice);
            $row = intval($choiceSplit[0]);
            $column = intval($choiceSplit[1]);
            $possibleAnswerPoints[$choice] = self::getPointsFromZombieChoice(
                $this->game,
                $playerCards,
                $roundNumber,
                $isFlowerPowerExpansion,
                $card,
                $row,
                $column,
            );
        }

        $maxPoints = max($possibleAnswerPoints);
        $maxPointsAnswers = array_keys($possibleAnswerPoints, $maxPoints);
        $zombieChoice = $maxPointsAnswers[bga_rand(0, count($maxPointsAnswers) - 1)];
        $zombieChoiceSplit = explode('-', $zombieChoice);
        $row = intval($zombieChoiceSplit[0]);
        $column = intval($zombieChoiceSplit[1]);
        $this->applyPlayCard($playerId, $row, $column);
    }

    /**
     * @param array<string,Card[]> $playerCards
     */
    public static function getPointsFromZombieChoice(
        Game $game,
        array $playerCards,
        int $roundNumber,
        bool $isFlowerPowerExpansion,
        Card $card,
        int $row,
        int $column,
    ): int {
        $playerCards[$row.'-'.$column] = array_merge($playerCards[$row.'-'.$column], [$card]);

        return $game->cardManager->getDetailledScore($playerCards, $roundNumber, $isFlowerPowerExpansion)->points;
    }

    private function applyPlayCard(int $playerId, int $row, int $column) {
        $card = $this->game->cardManager->getSelectedCard();
        $space = Game::getValueFromRowColumn($row, $column);

        $this->game->cardManager->playCard($playerId, $card, $row, $column);

        $statName = $space == $card->value ? 'cardPlayedEmptySpaceVisible' : 'cardPlayedEmptySpaceHidden';
        $this->playerStats->inc($statName, 1, $playerId, updateTableStat: true);

        $this->bga->notify->all('playCard', clienttranslate('${player_name} plays a ${color} card on space ${value}'), [
            'playerId' => $playerId,
            'card' => $space == $card->value ? $card : Card::onlyId($card),
            'space' => $space,
            'row' => $row,
            'column' => $column,
            'visibleCard' => $card, // only used for logs
        ]);

        if (!boolval($this->game->getGameStateValue((string)\LAST_TURN)) && $this->game->cardManager->getPlayerCardCount($playerId) >= 9) {
            $this->game->setGameStateValue((string)\LAST_TURN, 1);

            $this->notify->all('lastTurn', clienttranslate('${player_name} has filled all 9 of their spaces, triggering the end of the round!'), [
                'playerId' => $playerId,
            ]);
        }

        $this->gamestate->jumpToState(NextPlayer::class);
    }
}
