<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Множитель дистанции процесса.
 */
final class ProcessDistanceGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $abilityCode Поле.
     * @param int $multiplier Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $abilityCode,
        private readonly int $multiplier,
        private readonly bool $permanent,
    ) {
    }

    /**
     * Тип гранта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'process_distance_multiplier';
    }

    /**
     * Код.
     *
     * @return string Значение.
     */
    public function getAbilityCode(): string
    {
        return $this->abilityCode;
    }

    /**
     * Множитель.
     *
     * @return int Значение.
     */
    public function getMultiplier(): int
    {
        return $this->multiplier;
    }

    /**
     * Постоянный грант.
     *
     * @return bool Значение.
     */
    public function isPermanent(): bool
    {
        return $this->permanent;
    }
}
