<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar;

/**
 * Уровень способности.
 */
final class AbilityLevelScalar implements ScalarFormula
{
    /**
     * Создаёт узел.
     *
     * @param mixed $abilityCode Поле.
     * @param mixed $multiplier Поле.
     * @param mixed $offset Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly string $abilityCode,
        private readonly ?int $multiplier,
        private readonly ?int $offset,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'ability_level';
    }

    /**
     * Код способности.
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
     * @return ?int Значение.
     */
    public function getMultiplier(): ?int
    {
        return $this->multiplier;
    }

    /**
     * Смещение.
     *
     * @return ?int Значение.
     */
    public function getOffset(): ?int
    {
        return $this->offset;
    }
}
