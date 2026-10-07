<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Узкий контекст обхода формулы. Лист персонажа сюда не входит.
 */
final class FormulaContext
{
    /**
     * Создаёт контекст из значений, которые передал вызывающий.
     *
     * @param array<string, DimensionalNumber> $characteristics Характеристики.
     * @param array<string, int> $abilityLevels Уровни способностей.
     * @param array<string, int> $parameters Параметры.
     * @param array<string, array<string, DimensionalNumber>> $actionCharacteristics Базы характеристик действий.
     *
     * @return void
     */
    public function __construct(
        private readonly array $characteristics = [],
        private readonly array $abilityLevels = [],
        private readonly array $parameters = [],
        private readonly array $actionCharacteristics = [],
    ) {
    }

    /**
     * Значение характеристики.
     *
     * @param string $code Код.
     *
     * @return DimensionalNumber|null Пара или null.
     */
    public function findCharacteristic(string $code): ?DimensionalNumber
    {
        return $this->characteristics[$code] ?? null;
    }

    /**
     * Уровень способности. Нет ключа — ноль.
     *
     * @param string $code Код.
     *
     * @return int Уровень.
     */
    public function findAbilityLevel(string $code): int
    {
        return $this->abilityLevels[$code] ?? 0;
    }

    /**
     * Параметр. Нет ключа — null, это не ноль.
     *
     * @param string $code Код.
     *
     * @return int|null Значение или null.
     */
    public function findParameter(string $code): ?int
    {
        return $this->parameters[$code] ?? null;
    }

    /**
     * База характеристики действия.
     *
     * @param string $action Действие.
     * @param string $code Код характеристики.
     *
     * @return DimensionalNumber|null Пара или null.
     */
    public function findActionCharacteristic(string $action, string $code): ?DimensionalNumber
    {
        return $this->actionCharacteristics[$action][$code] ?? null;
    }
}
