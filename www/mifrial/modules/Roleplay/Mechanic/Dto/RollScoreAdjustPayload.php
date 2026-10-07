<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Дельты успехов за единицу и за грань. Хендлера этой сессии под payload нет.
 */
final class RollScoreAdjustPayload implements MechanicPayload
{
    /**
     * Собирает дельты.
     *
     * @param int|null $oneDelta Дельта за грань 1.
     * @param int|null $faceDelta Дельта за максимальную грань.
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $oneDelta = null,
        private readonly ?int $faceDelta = null,
    ) {
    }

    /**
     * Дельта за единицу.
     *
     * @return int|null Дельта или null.
     */
    public function getOneDelta(): ?int
    {
        return $this->oneDelta;
    }

    /**
     * Дельта за грань.
     *
     * @return int|null Дельта или null.
     */
    public function getFaceDelta(): ?int
    {
        return $this->faceDelta;
    }
}
