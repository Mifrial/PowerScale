<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;

/**
 * Все дочерние требования.
 */
final class AndRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param array<int, AbilityRequirement> $requirements Дети.
     *
     * @return void
     */
    public function __construct(private readonly array $requirements)
    {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'and';
    }

    /**
     * Дети.
     *
     * @return array<int, AbilityRequirement> Список.
     */
    public function getRequirements(): array
    {
        return $this->requirements;
    }
}
