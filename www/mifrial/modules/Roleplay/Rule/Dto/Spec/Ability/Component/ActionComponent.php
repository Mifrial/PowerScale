<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component;

/**
 * Компонент действия.
 */
interface ActionComponent
{
    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string;
}
