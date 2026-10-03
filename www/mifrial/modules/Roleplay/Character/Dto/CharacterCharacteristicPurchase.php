<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Закупка характеристики расы: код и потраченные очки.
 */
final class CharacterCharacteristicPurchase
{
    /**
     * Создаёт закупку.
     *
     * @param string $characteristicCode Код характеристики.
     * @param int $cost Потраченные очки. 0 — ступень не выбрана.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly int $cost,
    ) {
    }

    /**
     * Код характеристики.
     *
     * @return string Код.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Потраченные очки.
     *
     * @return int Цена ступени.
     */
    public function getCost(): int
    {
        return $this->cost;
    }
}
