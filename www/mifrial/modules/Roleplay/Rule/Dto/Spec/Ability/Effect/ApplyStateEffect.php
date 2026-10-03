<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Ветка apply_state.
 */
final class ApplyStateEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $stateCode Состояние.
     * @param int|DimensionalNumber|null $amount Количество.
     *
     * @return void
     */
    public function __construct(
        private readonly string $stateCode,
        private readonly int|DimensionalNumber|null $amount,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'apply_state';
    }

    /**
     * Состояние.
     *
     * @return string Значение.
     */
    public function getStateCode(): string
    {
        return $this->stateCode;
    }

    /**
     * Количество.
     *
     * @return int|DimensionalNumber|null Значение.
     */
    public function getAmount(): int|DimensionalNumber|null
    {
        return $this->amount;
    }
}
