<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition;

/**
 * Переход между шагами процесса.
 */
interface ProcessTransition
{
    /**
     * Режим перехода.
     *
     * @return string Код.
     */
    public function getMode(): string;
}
