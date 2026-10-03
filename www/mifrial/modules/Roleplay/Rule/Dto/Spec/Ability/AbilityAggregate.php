<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Агрегат уровня по числу методов.
 */
final class AbilityAggregate
{
    /**
     * Создаёт агрегат.
     *
     * @param string $characteristicCode Характеристика бонуса.
     * @param string $methodKeyword Признак метода.
     * @param array<int, int> $levels Пороги по ступеням.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly string $methodKeyword,
        private readonly array $levels,
    ) {
    }

    /**
     * Характеристика.
     *
     * @return string Код.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Признак метода.
     *
     * @return string Код.
     */
    public function getMethodKeyword(): string
    {
        return $this->methodKeyword;
    }

    /**
     * Пороги.
     *
     * @return array<int, int> Список.
     */
    public function getLevels(): array
    {
        return $this->levels;
    }
}
