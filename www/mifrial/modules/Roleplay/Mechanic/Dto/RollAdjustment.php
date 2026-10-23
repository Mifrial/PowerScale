<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

/**
 * Настройка броска из карточки: ремап граней и дельты успехов.
 */
final class RollAdjustment
{
    /**
     * Создаёт настройку.
     *
     * @param array<int, int> $remap Карта грань → грань.
     * @param int $oneDelta Успехи за каждую единицу.
     * @param int $faceDelta Успехи за максимальную грань выше эффективности.
     *
     * @return void
     */
    public function __construct(
        private readonly array $remap = [],
        private readonly int $oneDelta = 0,
        private readonly int $faceDelta = 0,
    ) {
    }

    /**
     * Карта ремапа.
     *
     * @return array<int, int> Грань → грань.
     */
    public function getRemap(): array
    {
        return $this->remap;
    }

    /**
     * Дельта успехов за единицу.
     *
     * @return int Значение.
     */
    public function getOneDelta(): int
    {
        return $this->oneDelta;
    }

    /**
     * Дельта успехов за максимальную грань.
     *
     * @return int Значение.
     */
    public function getFaceDelta(): int
    {
        return $this->faceDelta;
    }

    /**
     * Ничего не меняет.
     *
     * @return bool true, если настройка пустая.
     */
    public function isEmpty(): bool
    {
        return $this->remap === [] && $this->oneDelta === 0 && $this->faceDelta === 0;
    }
}
