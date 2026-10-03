<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation;

/**
 * Операция шага процесса.
 */
interface ProcessOperation
{
    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string;
}
