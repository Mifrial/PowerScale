<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Ветка require_previous_attack.
 */
final class RequirePreviousAttackEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param bool $sameTarget Та же цель.
     * @param bool $allDamaged Все с повреждением.
     * @param bool $singleStrike Один удар.
     *
     * @return void
     */
    public function __construct(
        private readonly bool $sameTarget,
        private readonly bool $allDamaged,
        private readonly bool $singleStrike,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'require_previous_attack';
    }

    /**
     * Та же цель.
     *
     * @return bool Значение.
     */
    public function isSameTarget(): bool
    {
        return $this->sameTarget;
    }

    /**
     * Все с повреждением.
     *
     * @return bool Значение.
     */
    public function isAllDamaged(): bool
    {
        return $this->allDamaged;
    }

    /**
     * Один удар.
     *
     * @return bool Значение.
     */
    public function isSingleStrike(): bool
    {
        return $this->singleStrike;
    }
}
