<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;

/**
 * Поправка лимита ресурса.
 */
final class ResourceAdjustment
{
    /**
     * Создаёт поправку.
     *
     * @param ScalarFormula $value Величина.
     * @param string $sourceCode Источник.
     *
     * @return void
     */
    public function __construct(
        private readonly ScalarFormula $value,
        private readonly string $sourceCode,
    ) {
    }

    /**
     * Величина.
     *
     * @return ScalarFormula Узел.
     */
    public function getValue(): ScalarFormula
    {
        return $this->value;
    }

    /**
     * Источник.
     *
     * @return string Код.
     */
    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }
}
