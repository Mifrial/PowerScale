<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar;

/**
 * Узел скалярной формулы.
 */
interface ScalarFormula
{
    /**
     * Дискриминатор узла.
     *
     * @return string Тип.
     */
    public function getNode(): string;
}
