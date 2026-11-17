<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;

/**
 * Нужно знание типа не ниже уровня.
 */
final class HasKnowledgeRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param string $fieldCode Тип знания.
     * @param int|null $minLevel Минимум уровня.
     *
     * @return void
     */
    public function __construct(
        private readonly string $fieldCode,
        private readonly ?int $minLevel,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'has_knowledge';
    }

    /**
     * Тип знания.
     *
     * @return string Код.
     */
    public function getFieldCode(): string
    {
        return $this->fieldCode;
    }

    /**
     * Минимум уровня.
     *
     * @return int|null Уровень или null.
     */
    public function getMinLevel(): ?int
    {
        return $this->minLevel;
    }
}
