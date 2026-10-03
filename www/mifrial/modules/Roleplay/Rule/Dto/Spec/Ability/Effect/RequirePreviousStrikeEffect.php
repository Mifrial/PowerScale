<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Ветка require_previous_strike.
 */
final class RequirePreviousStrikeEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $minSr Минимум успеха.
     * @param string $notKind Исключённый вид.
     * @param bool $sameTarget Та же цель.
     *
     * @return void
     */
    public function __construct(
        private readonly int $minSr,
        private readonly string $notKind,
        private readonly bool $sameTarget,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'require_previous_strike';
    }

    /**
     * Минимум успеха.
     *
     * @return int Значение.
     */
    public function getMinSr(): int
    {
        return $this->minSr;
    }

    /**
     * Исключённый вид.
     *
     * @return string Значение.
     */
    public function getNotKind(): string
    {
        return $this->notKind;
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
}
