<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

/**
 * Одна строка детализации доплаты: способность и сумма в ОС.
 */
final class SurchargeItem
{
    /**
     * Собирает строку детализации.
     *
     * @param string $abilityCode Код способности.
     * @param int $amount Сумма доплаты, в ОС.
     *
     * @return void
     */
    public function __construct(
        private readonly string $abilityCode,
        private readonly int $amount,
    ) {
    }

    /**
     * Код способности.
     *
     * @return string Код.
     */
    public function getAbilityCode(): string
    {
        return $this->abilityCode;
    }

    /**
     * Сумма доплаты.
     *
     * @return int ОС.
     */
    public function getAmount(): int
    {
        return $this->amount;
    }
}
