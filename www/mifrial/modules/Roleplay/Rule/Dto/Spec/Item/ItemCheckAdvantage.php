<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

/**
 * Помеха предмета на проверки характеристик.
 */
final class ItemCheckAdvantage
{
    /**
     * Создаёт помеху.
     *
     * @param int $delta Величина.
     * @param array<int, string> $characteristicCodes Характеристики.
     * @param bool $includesHit Проверки попадания.
     *
     * @return void
     */
    public function __construct(
        private readonly int $delta,
        private readonly array $characteristicCodes,
        private readonly bool $includesHit,
    ) {
    }

    /**
     * Величина.
     *
     * @return int Число.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }

    /**
     * Характеристики.
     *
     * @return array<int, string> Коды.
     */
    public function getCharacteristicCodes(): array
    {
        return $this->characteristicCodes;
    }

    /**
     * Проверки попадания.
     *
     * @return bool true, если включает.
     */
    public function isIncludesHit(): bool
    {
        return $this->includesHit;
    }
}
