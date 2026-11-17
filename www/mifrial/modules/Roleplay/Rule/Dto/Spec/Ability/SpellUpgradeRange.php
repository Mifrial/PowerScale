<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Дальность каста: замена касания выбором цели на расстоянии.
 */
final class SpellUpgradeRange
{
    /**
     * Создаёт форму дальности.
     *
     * @param int $powerPerStep Пунктов Требуемой мощи за шаг дистанции.
     * @param int $controlBase Добавка к Требуемому контролю.
     *
     * @return void
     */
    public function __construct(
        private readonly int $powerPerStep,
        private readonly int $controlBase,
    ) {
    }

    /**
     * Пунктов мощи за шаг.
     *
     * @return int Число.
     */
    public function getPowerPerStep(): int
    {
        return $this->powerPerStep;
    }

    /**
     * Добавка к контролю.
     *
     * @return int Число.
     */
    public function getControlBase(): int
    {
        return $this->controlBase;
    }
}
