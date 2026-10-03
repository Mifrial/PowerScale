<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance;

/**
 * Дистанция операции процесса.
 */
interface ProcessDistance
{
    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string;
}
