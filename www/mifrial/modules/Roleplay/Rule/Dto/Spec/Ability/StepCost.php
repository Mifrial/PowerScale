<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Цена шага процесса.
 */
final class StepCost
{
    /**
     * Создаёт цену.
     *
     * @param string $resourceCode Ресурс.
     * @param int|DimensionalNumber $amount Количество.
     *
     * @return void
     */
    public function __construct(
        private readonly string $resourceCode,
        private readonly int|DimensionalNumber $amount,
    ) {
    }

    /**
     * Ресурс.
     *
     * @return string Код.
     */
    public function getResourceCode(): string
    {
        return $this->resourceCode;
    }

    /**
     * Количество.
     *
     * @return int|DimensionalNumber Значение.
     */
    public function getAmount(): int|DimensionalNumber
    {
        return $this->amount;
    }
}
