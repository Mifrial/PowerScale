<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Безусловный срез цены названного действия.
 */
final class ActionResourceCutGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $actionRuleCode Действие.
     * @param string $resourceCode Ресурс.
     * @param int $amount Сколько снять.
     * @param bool $permanent Постоянный.
     *
     * @return void
     */
    public function __construct(
        private readonly string $actionRuleCode,
        private readonly string $resourceCode,
        private readonly int $amount,
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
        return 'action_resource_cut';
    }

    /**
     * Действие.
     *
     * @return string Код.
     */
    public function getActionRuleCode(): string
    {
        return $this->actionRuleCode;
    }

    /**
     * Ресурс.
     *
     * @return string Код.
     */
    public function getResourceCode(): string
    {
        return $this->resourceCode;
    }

    /**
     * Срез.
     *
     * @return int Величина.
     */
    public function getAmount(): int
    {
        return $this->amount;
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
