<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

/**
 * Предмет-хранилище магии: кристалл с Предельной мощью и Объёмом.
 *
 * Мощь экземпляра лежит в `parameters`, выбранный Объём — в `payload` строки
 * инвентаря. Объём покупается ступенями и не может быть меньше удвоенной
 * Предельной мощи.
 */
final class ItemStorage
{
    /**
     * Создаёт блок.
     *
     * @param string $powerParameter Код параметра экземпляра с Предельной мощью.
     * @param string $volumeParameter Код поля payload с Объёмом.
     * @param string $recordedParameter Код поля payload с записями заклинаний или ''.
     * @param int $volumeBase Наименьшая ступень Объёма.
     * @param int $volumeIncrement Шаг ступени Объёма.
     * @param int $minVolumePowerDelta Прибавка к размерности мощи для минимального Объёма.
     * @param array<int, string> $recordedKeywords Признаки, которыми ограничены записи; пусто — без ограничения.
     *
     * @return void
     */
    public function __construct(
        private readonly string $powerParameter,
        private readonly string $volumeParameter,
        private readonly string $recordedParameter,
        private readonly int $volumeBase,
        private readonly int $volumeIncrement,
        private readonly int $minVolumePowerDelta,
        private readonly array $recordedKeywords = [],
    ) {
    }

    /**
     * Код параметра экземпляра с Предельной мощью.
     *
     * @return string Код.
     */
    public function getPowerParameter(): string
    {
        return $this->powerParameter;
    }

    /**
     * Код поля payload с Объёмом.
     *
     * @return string Код.
     */
    public function getVolumeParameter(): string
    {
        return $this->volumeParameter;
    }

    /**
     * Код поля payload с записями заклинаний.
     *
     * @return string Код или ''.
     */
    public function getRecordedParameter(): string
    {
        return $this->recordedParameter;
    }

    /**
     * Наименьшая ступень Объёма.
     *
     * @return int Значение.
     */
    public function getVolumeBase(): int
    {
        return $this->volumeBase;
    }

    /**
     * Шаг ступени Объёма.
     *
     * @return int Значение.
     */
    public function getVolumeIncrement(): int
    {
        return $this->volumeIncrement;
    }

    /**
     * Прибавка к размерности мощи для минимального Объёма.
     *
     * @return int Пункты шкалы.
     */
    public function getMinVolumePowerDelta(): int
    {
        return $this->minVolumePowerDelta;
    }

    /**
     * Признаки, которыми ограничены записи.
     *
     * @return array<int, string> Коды; пусто — без ограничения.
     */
    public function getRecordedKeywords(): array
    {
        return $this->recordedKeywords;
    }
}
