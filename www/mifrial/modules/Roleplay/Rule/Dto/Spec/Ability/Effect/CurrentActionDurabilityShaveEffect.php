<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;

/**
 * Ветка current_action_durability_shave.
 */
final class CurrentActionDurabilityShaveEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param bool $shortExtraOnFirstOne Короткий лишний.
     * @param AttackScope $scope Область.
     * @param array $damageTypeCodes Типы урона.
     *
     * @return void
     */
    public function __construct(
        private readonly bool $shortExtraOnFirstOne,
        private readonly AttackScope $scope,
        private readonly array $damageTypeCodes,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'current_action_durability_shave';
    }

    /**
     * Короткий лишний.
     *
     * @return bool Значение.
     */
    public function isShortExtraOnFirstOne(): bool
    {
        return $this->shortExtraOnFirstOne;
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
     * Типы урона.
     *
     * @return array Значение.
     */
    public function getDamageTypeCodes(): array
    {
        return $this->damageTypeCodes;
    }
}
