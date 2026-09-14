<?php

declare(strict_types=1);

namespace Bga\Games\Pixies;

use Bga\GameFramework\Components\ItemManager\ItemLocation;
use Bga\GameFramework\Components\ItemManager\ItemManager;
use Bga\GameFramework\Helpers\Collection;
use Bga\Games\Pixies\Objects\Card;

class CardManager
{
    public ItemManager $cards;

    public function __construct(private Game $game)
    {
        $this->cards = $game->bga->itemManagerFactory->createItemManager(
            Card::class,
            locations: [
                new ItemLocation('table'),
            ],
        );
    }

    public function initDb(): void
    {
        $this->cards->initDb();
    }

    /*public function setup(): void
    {
        $cards = [];
        foreach ($this->game->card_types['suites'] as $suit => $suitInfo) {
            foreach ($this->game->card_types['types'] as $value => $valueInfo) {
                $cards[] = [
                    'location' => 'deck',
                    'suit' => $suit,
                    'value' => $value,
                ];
            }
        }
        $this->cards->createItems($cards);
    }*/

    /** @return Collection<Card> */
    public function getTableCards(): Collection
    {
        return $this->cards->getItemsInLocation(['table']);
    }
}