<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula;

/**
 * Шаги плюс разрыв размеров двух характеристик.
 */
final class SizeGapTimesStepNode implements DimensionalFormula
{
    /**
     * Создаёт узел.
     *
     * @param string $fromCode Первая характеристика.
     * @param string $toCode Вторая характеристика.
     * @param int $baseSteps База шагов.
     * @param int $gapMultiplier Множитель разрыва.
     *
     * @return void
     */
    public function __construct(
        private readonly string $fromCode,
        private readonly string $toCode,
        private readonly int $baseSteps,
        private readonly int $gapMultiplier,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'size_gap_times_step';
    }

    /**
     * Первая характеристика.
     *
     * @return string Код.
     */
    public function getFromCode(): string
    {
        return $this->fromCode;
    }

    /**
     * Вторая характеристика.
     *
     * @return string Код.
     */
    public function getToCode(): string
    {
        return $this->toCode;
    }

    /**
     * База шагов.
     *
     * @return int Значение.
     */
    public function getBaseSteps(): int
    {
        return $this->baseSteps;
    }

    /**
     * Множитель разрыва.
     *
     * @return int Значение.
     */
    public function getGapMultiplier(): int
    {
        return $this->gapMultiplier;
    }
}
