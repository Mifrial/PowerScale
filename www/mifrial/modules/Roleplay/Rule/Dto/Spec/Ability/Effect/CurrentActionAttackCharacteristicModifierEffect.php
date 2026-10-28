<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;

/**
 * Ветка current_action_attack_characteristic_modifier.
 */
final class CurrentActionAttackCharacteristicModifierEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $delta Сдвиг.
     * @param AttackScope $scope Область.
     * @param ?int $minOccupyHands Минимум рук.
     * @param array $damageTypeCodes Типы урона.
     * @param ?string $sourceCode Источник.
     *
     * @return void
     */
    public function __construct(
        private readonly int $delta,
        private readonly AttackScope $scope,
        private readonly ?int $minOccupyHands,
        private readonly array $damageTypeCodes,
        private readonly ?string $sourceCode = null,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'current_action_attack_characteristic_modifier';
    }

    /**
     * Сдвиг.
     *
     * @return int Значение.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }

    /**
     * Область.
     *
     * @return AttackScope Значение.
     */
    public function getScope(): AttackScope
    {
        return $this->scope;
    }

    /**
     * Минимум рук.
     *
     * @return ?int Значение.
     */
    public function getMinOccupyHands(): ?int
    {
        return $this->minOccupyHands;
    }

    /**
     * Типы урона.
     *
     * @return array Значение.
     */
    public function getDamageTypeCodes(): array
    {
        return $this->damageTypeCodes;
    }

    /**
     * Источник.
     *
     * @return ?string Значение.
     */
    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }
}
