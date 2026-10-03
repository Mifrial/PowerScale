<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition;

/**
 * Свободный переход. Нет ключа transition — этот вариант.
 */
final class FreeTransition implements ProcessTransition
{
    /**
     * Режим.
     *
     * @return string Код.
     */
    public function getMode(): string
    {
        return 'free';
    }
}
