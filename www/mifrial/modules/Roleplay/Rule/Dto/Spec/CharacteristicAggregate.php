<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Агрегат характеристики: база плюс вклады других характеристик.
 *
 * Каждый вклад — превышение характеристики над опорным значением в пунктах;
 * сумма делится на шаг, и результат сдвигает базу по шкале характеристик.
 * Духовность: база 3↑, вклады Общения/Силы воли/Внимательности относительно
 * 5, один пункт за каждые два пункта суммы.
 */
final class CharacteristicAggregate
{
    /**
     * Создаёт агрегат.
     *
     * @param CharacteristicNumber $base База.
     * @param array<int, string> $sourceCodes Характеристики-источники.
     * @param CharacteristicNumber $reference Опорное значение вклада.
     * @param int $pointsPerStep Сколько пунктов суммы дают один шаг.
     * @param int $step Сдвиг базы за шаг.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacteristicNumber $base,
        private readonly array $sourceCodes,
        private readonly CharacteristicNumber $reference,
        private readonly int $pointsPerStep,
        private readonly int $step,
    ) {
    }

    /**
     * База.
     *
     * @return CharacteristicNumber База.
     */
    public function getBase(): CharacteristicNumber
    {
        return $this->base;
    }

    /**
     * Характеристики-источники.
     *
     * @return array<int, string> Коды.
     */
    public function getSourceCodes(): array
    {
        return $this->sourceCodes;
    }

    /**
     * Опорное значение вклада.
     *
     * @return CharacteristicNumber Значение.
     */
    public function getReference(): CharacteristicNumber
    {
        return $this->reference;
    }

    /**
     * Пунктов суммы на шаг.
     *
     * @return int Число.
     */
    public function getPointsPerStep(): int
    {
        return $this->pointsPerStep;
    }

    /**
     * Сдвиг базы за шаг.
     *
     * @return int Число.
     */
    public function getStep(): int
    {
        return $this->step;
    }
}
