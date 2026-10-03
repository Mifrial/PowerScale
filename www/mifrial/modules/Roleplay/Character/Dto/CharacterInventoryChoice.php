<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Экземпляр предмета: код правила и признак надетости.
 */
final class CharacterInventoryChoice
{
    /**
     * Создаёт выбор.
     *
     * @param string $ruleCode Код предмета.
     * @param bool $equipped Надет.
     * @param int $quantity Количество. Нет ключа во входе — 1.
     *
     * @return void
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly bool $equipped,
        private readonly int $quantity = 1,
    ) {
    }

    /**
     * Код предмета.
     *
     * @return string Код.
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * Признак надетости.
     *
     * @return bool true, если надет.
     */
    public function isEquipped(): bool
    {
        return $this->equipped;
    }

    /**
     * Количество.
     *
     * @return int Число.
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
