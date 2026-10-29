<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;

/**
 * Модификатор действия: момент, область, необязательная проверка и последствия.
 */
final class ActionModifierEffect implements ActionEffect
{
    /**
     * Создаёт модификатор.
     *
     * @param string $trigger Момент срабатывания.
     * @param AttackScope $scope Область действия.
     * @param ModifierCheck|null $check Проверка или null.
     * @param array<int, ModifierOutcomeEffect> $effects Последствия при успехе.
     *
     * @return void
     */
    public function __construct(
        private readonly string $trigger,
        private readonly AttackScope $scope,
        private readonly ?ModifierCheck $check,
        private readonly array $effects,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'action_modifier';
    }

    /**
     * Момент срабатывания.
     *
     * @return string Значение.
     */
    public function getTrigger(): string
    {
        return $this->trigger;
    }

    /**
     * Область действия.
     *
     * @return AttackScope Значение.
     */
    public function getScope(): AttackScope
    {
        return $this->scope;
    }

    /**
     * Проверка.
     *
     * @return ModifierCheck|null Значение.
     */
    public function getCheck(): ?ModifierCheck
    {
        return $this->check;
    }

    /**
     * Последствия при успехе.
     *
     * @return array<int, ModifierOutcomeEffect> Значения.
     */
    public function getEffects(): array
    {
        return $this->effects;
    }

    /**
     * Применим ли модификатор к этому компоненту и попаданию.
     *
     * @param string $component Вид профиля: strike, throw или shoot.
     * @param int $hitNumber Номер попадания с единицы.
     *
     * @return bool true, если применим.
     */
    public function appliesTo(string $component, int $hitNumber): bool
    {
        return $this->scope->appliesTo($component, $hitNumber);
    }
}
