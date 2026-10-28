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
     * @param int $actionCharacteristicModifier Сдвиг характеристики действия.
     *
     * @return void
     */
    public function __construct(
        private readonly array $characteristics = [],
        private readonly array $abilityLevels = [],
        private readonly array $parameters = [],
        private readonly array $actionCharacteristics = [],
        private readonly int $actionCharacteristicModifier = 0,
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

    /**
     * Дописывает характеристики, не затирая уже заданные закупкой.
     *
     * @param array<string, DimensionalNumber> $defaults Автоматические значения.
     *
     * @return self Контекст.
     */
    public function mergeCharacteristics(array $defaults): self
    {
        return new self(
            $this->characteristics + $defaults,
            $this->abilityLevels,
            $this->parameters,
            $this->actionCharacteristics,
            $this->actionCharacteristicModifier,
        );
    }

    /**
     * Копия контекста с заменённой характеристикой.
     *
     * @param string $code Код характеристики.
     * @param DimensionalNumber $value Значение.
     *
     * @return self Контекст.
     */
    public function withCharacteristic(string $code, DimensionalNumber $value): self
    {
        $characteristics = $this->characteristics;
        $characteristics[$code] = $value;

        return new self(
            $characteristics,
            $this->abilityLevels,
            $this->parameters,
            $this->actionCharacteristics,
            $this->actionCharacteristicModifier,
        );
    }

    /**
     * Копия контекста с базой характеристики действия.
     *
     * @param string $action Действие.
     * @param string $code Код характеристики.
     * @param DimensionalNumber $value База.
     *
     * @return self Контекст.
     */
    public function withActionCharacteristic(string $action, string $code, DimensionalNumber $value): self
    {
        $actionCharacteristics = $this->actionCharacteristics;
        $actionCharacteristics[$action][$code] = $value;

        return new self(
            $this->characteristics,
            $this->abilityLevels,
            $this->parameters,
            $actionCharacteristics,
            $this->actionCharacteristicModifier,
        );
    }

    /**
     * Копия контекста с базами характеристик действий. Базы аргумента важнее
     * уже заданных.
     *
     * @param array<string, array<string, DimensionalNumber>> $actionCharacteristics Базы.
     *
     * @return self Контекст.
     */
    public function withActionCharacteristics(array $actionCharacteristics): self
    {
        $merged = $this->actionCharacteristics;
        foreach ($actionCharacteristics as $action => $values) {
            $merged[$action] = $values + ($merged[$action] ?? []);
        }

        return new self(
            $this->characteristics,
            $this->abilityLevels,
            $this->parameters,
            $merged,
            $this->actionCharacteristicModifier,
        );
    }

    /**
     * Сдвиг характеристики действия в этом контексте.
     *
     * @return int Пункты.
     */
    public function getActionCharacteristicModifier(): int
    {
        return $this->actionCharacteristicModifier;
    }

    /**
     * Копия контекста со сдвигом характеристики действия.
     *
     * @param int $delta Пункты.
     *
     * @return self Контекст.
     */
    public function withActionCharacteristicModifier(int $delta): self
    {
        return new self(
            $this->characteristics,
            $this->abilityLevels,
            $this->parameters,
            $this->actionCharacteristics,
            $delta,
        );
    }
}
