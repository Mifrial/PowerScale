<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component;

/**
 * Количество «сколько есть»: type chosen, max available.
 */
final class ChosenAmount
{
    /**
     * Вид количества.
     *
     * @return string Код.
     */
    public function getKind(): string
    {
        return 'chosen';
    }
}
