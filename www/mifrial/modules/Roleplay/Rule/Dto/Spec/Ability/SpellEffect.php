<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Эффект заклинания.
 */
interface SpellEffect
{
    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string;
}
