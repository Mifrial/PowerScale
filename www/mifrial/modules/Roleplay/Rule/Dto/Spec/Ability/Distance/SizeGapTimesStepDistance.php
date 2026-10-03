<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance;

/**
 * Ветка size_gap_times_step.
 */
final class SizeGapTimesStepDistance implements ProcessDistance
{
    /**
     * Создаёт ветку.
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
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'size_gap_times_step';
    }

    /**
     * Первая характеристика.
     *
     * @return string Значение.
     */
    public function getFromCode(): string
    {
        return $this->fromCode;
    }

    /**
     * Вторая характеристика.
     *
     * @return string Значение.
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
