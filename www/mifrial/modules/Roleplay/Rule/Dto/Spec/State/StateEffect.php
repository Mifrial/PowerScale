<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

/**
 * Эффект состояния.
 */
interface StateEffect
{
    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string;
}
