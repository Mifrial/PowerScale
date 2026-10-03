<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Грант изучения навыка.
 */
final class SkillStudyGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param array $abilityCodes Поле.
     * @param int $maxLevel Поле.
     * @param int $paidCost Поле.
     * @param ?int $maxInstances Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly array $abilityCodes,
        private readonly int $maxLevel,
        private readonly int $paidCost,
        private readonly ?int $maxInstances,
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
        return 'skill_study';
    }

    /**
     * Коды.
     *
     * @return array Значение.
     */
    public function getAbilityCodes(): array
    {
        return $this->abilityCodes;
    }

    /**
     * Максимальный уровень.
     *
     * @return int Значение.
     */
    public function getMaxLevel(): int
    {
        return $this->maxLevel;
    }

    /**
     * Оплата.
     *
     * @return int Значение.
     */
    public function getPaidCost(): int
    {
        return $this->paidCost;
    }

    /**
     * Лимит экземпляров.
     *
     * @return ?int Значение.
     */
    public function getMaxInstances(): ?int
    {
        return $this->maxInstances;
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
