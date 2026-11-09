<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Применённое улучшение каста.
 */
final class GameSpellCastAppliedUpgrade
{
    /**
     * Собирает запись.
     *
     * @param string $ruleCode Код способности.
     * @param string $name Подпись способности.
     * @param int $value Выбранное значение шага.
     * @param int $actionPointDelta Добавка ОД.
     * @param int $checkAdvantage Преимущество сотворения.
     * @param int $resistancePenetration Пробитие сопротивления.
     * @param int $requiredPowerDelta Добавка Требуемой мощи.
     *
     * @return void
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly string $name,
        private readonly int $value,
        private readonly int $actionPointDelta,
        private readonly int $checkAdvantage,
        private readonly int $resistancePenetration,
        private readonly int $requiredPowerDelta,
    ) {
    }

    /**
     * Код способности.
     *
     * @return string Код.
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * Подпись.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Выбранное значение.
     *
     * @return int Значение.
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * Добавка ОД.
     *
     * @return int Число.
     */
    public function getActionPointDelta(): int
    {
        return $this->actionPointDelta;
    }

    /**
     * Преимущество сотворения.
     *
     * @return int Число.
     */
    public function getCheckAdvantage(): int
    {
        return $this->checkAdvantage;
    }

    /**
     * Пробитие сопротивления.
     *
     * @return int Число.
     */
    public function getResistancePenetration(): int
    {
        return $this->resistancePenetration;
    }

    /**
     * Добавка Требуемой мощи.
     *
     * @return int Число.
     */
    public function getRequiredPowerDelta(): int
    {
        return $this->requiredPowerDelta;
    }
}
