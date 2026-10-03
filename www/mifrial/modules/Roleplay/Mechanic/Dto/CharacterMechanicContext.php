<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

/**
 * Контекст шага сборки: узкий снимок и мутабельные аккумуляторы доплаты.
 */
final class CharacterMechanicContext
{
    /**
     * Накопленная доплата шага, в ОС.
     */
    private int $osSurchargeTotal = 0;

    /**
     * Строки детализации доплаты.
     *
     * @var array<int, SurchargeItem>
     */
    private array $surchargeItems = [];

    /**
     * Собирает контекст шага с нулевой доплатой.
     *
     * @param MechanicState $state Снимок способностей.
     *
     * @return void
     */
    public function __construct(
        private readonly MechanicState $state,
    ) {
    }

    /**
     * Снимок способностей.
     *
     * @return MechanicState Снимок.
     */
    public function getState(): MechanicState
    {
        return $this->state;
    }

    /**
     * Итог доплаты шага.
     *
     * @return int ОС.
     */
    public function getOsSurchargeTotal(): int
    {
        return $this->osSurchargeTotal;
    }

    /**
     * Детализация доплаты.
     *
     * @return array<int, SurchargeItem> Строки.
     */
    public function getSurchargeItems(): array
    {
        return $this->surchargeItems;
    }

    /**
     * Прибавляет доплату к итогу и в детализацию.
     *
     * @param string $abilityCode Код способности.
     * @param int $amount Сумма, в ОС.
     *
     * @return void
     */
    public function addSurcharge(string $abilityCode, int $amount): void
    {
        $this->osSurchargeTotal += $amount;
        $this->surchargeItems[] = new SurchargeItem($abilityCode, $amount);
    }
}
