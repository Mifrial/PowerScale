<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Требование способности.
 */
interface AbilityRequirement
{
    /**
     * Вид требования.
     *
     * @return string Код.
     */
    public function getType(): string;
}
