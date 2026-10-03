<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Одна ветка гранта способности.
 */
interface AbilityGrant
{
    /**
     * Дискриминатор ветки.
     *
     * @return string Тип.
     */
    public function getType(): string;
}
