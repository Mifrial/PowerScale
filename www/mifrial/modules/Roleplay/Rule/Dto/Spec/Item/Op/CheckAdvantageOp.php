<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка check_advantage.
 */
final class CheckAdvantageOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param int $delta Сдвиг.
     * @param array $characteristicCodes Характеристики.
     * @param bool $includesHit Попадание.
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
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'check_advantage';
    }

    /**
     * Сдвиг.
     *
     * @return int Значение.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }

    /**
     * Характеристики.
     *
     * @return array Значение.
     */
    public function getCharacteristicCodes(): array
    {
        return $this->characteristicCodes;
    }

    /**
     * Попадание.
     *
     * @return bool Значение.
     */
    public function isIncludesHit(): bool
    {
        return $this->includesHit;
    }
}
