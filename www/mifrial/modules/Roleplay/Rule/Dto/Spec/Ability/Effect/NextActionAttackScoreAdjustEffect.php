<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Ветка next_action_attack_score_adjust.
 */
final class NextActionAttackScoreAdjustEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $oneDelta Сдвиг единицы.
     * @param int $faceDelta Сдвиг грани.
     * @param bool $sameTarget Та же цель.
     * @param ?string $targetKey Ключ цели.
     *
     * @return void
     */
    public function __construct(
        private readonly int $oneDelta,
        private readonly int $faceDelta,
        private readonly bool $sameTarget,
        private readonly ?string $targetKey,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'next_action_attack_score_adjust';
    }

    /**
     * Сдвиг единицы.
     *
     * @return int Значение.
     */
    public function getOneDelta(): int
    {
        return $this->oneDelta;
    }

    /**
     * Сдвиг грани.
     *
     * @return int Значение.
     */
    public function getFaceDelta(): int
    {
        return $this->faceDelta;
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
     * Ключ цели.
     *
     * @return ?string Значение.
     */
    public function getTargetKey(): ?string
    {
        return $this->targetKey;
    }
}
