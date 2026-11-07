<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;

/**
 * Расчёт одиночного удара: прикрытие, защита и исход.
 */
final class GameStrikeSingleResolve
{
    /**
     * Создаёт расчёт удара.
     *
     * @param GameStrikeSingleCoverPhase $cover Прикрытие.
     * @param GameStrikeSingleRound $round Броски защиты.
     * @param GameStrikeSingleHits $outcome Исход.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSingleCoverPhase $cover,
        private readonly GameStrikeSingleRound $round,
        private readonly GameStrikeSingleHits $outcome,
    ) {
    }

    /**
     * Считает удар или возвращает ранний итог.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, mixed>|null Ранний итог.
     */
    public function resolve(GameStrikeSingleRun $run): ?array
    {
        $early = $this->cover->apply($run);
        if ($early !== null) {
            return $early;
        }

        $early = $this->round->resolve($run);
        if ($early !== null) {
            return $early;
        }

        $this->outcome->apply($run);

        return null;
    }
}
