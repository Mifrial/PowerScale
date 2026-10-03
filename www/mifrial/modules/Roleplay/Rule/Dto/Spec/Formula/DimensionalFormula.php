<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula;

/**
 * Узел размерной формулы.
 */
interface DimensionalFormula
{
    /**
     * Дискриминатор узла.
     *
     * @return string Тип.
     */
    public function getNode(): string;
}
