<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Ветка optional_after_strike_check.
 */
final class OptionalAfterStrikeCheckEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $checkCode Проверка.
     * @param int $difficulty Сложность.
     * @param bool $skipParentPending Снять ожидание родителя.
     * @param SelfDamage $selfDamage Урон себе.
     *
     * @return void
     */
    public function __construct(
        private readonly string $checkCode,
        private readonly int $difficulty,
        private readonly bool $skipParentPending,
        private readonly SelfDamage $selfDamage,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'optional_after_strike_check';
    }

    /**
     * Проверка.
     *
     * @return string Значение.
     */
    public function getCheckCode(): string
    {
        return $this->checkCode;
    }

    /**
     * Сложность.
     *
     * @return int Значение.
     */
    public function getDifficulty(): int
    {
        return $this->difficulty;
    }

    /**
     * Снять ожидание родителя.
     *
     * @return bool Значение.
     */
    public function isSkipParentPending(): bool
    {
        return $this->skipParentPending;
    }

    /**
     * Урон себе.
     *
     * @return SelfDamage Значение.
     */
    public function getSelfDamage(): SelfDamage
    {
        return $this->selfDamage;
    }
}
