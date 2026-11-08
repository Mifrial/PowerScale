<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Сопротивление.
 */
final class ResistanceGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $damageTypeCode Поле.
     * @param string $sourceCode Поле.
     * @param DimensionalNumber|DimensionalFormula|ScalarFormula $value Поле.
     * @param bool $permanent Поле.
     * @param bool $protectsInternal Держит ли грант внутренний урон.
     *
     * @return void
     */
    public function __construct(
        private readonly string $damageTypeCode,
        private readonly string $sourceCode,
        private readonly DimensionalNumber|DimensionalFormula|ScalarFormula $value,
        private readonly bool $permanent,
        private readonly bool $protectsInternal = false,
    ) {
    }

    /**
     * Тип гранта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'resistance';
    }

    /**
     * Тип урона.
     *
     * @return string Значение.
     */
    public function getDamageTypeCode(): string
    {
        return $this->damageTypeCode;
    }

    /**
     * Источник.
     *
     * @return string Значение.
     */
    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    /**
     * Величина.
     *
     * @return DimensionalNumber|DimensionalFormula|ScalarFormula Значение.
     */
    public function getValue(): DimensionalNumber|DimensionalFormula|ScalarFormula
    {
        return $this->value;
    }

    /**
     * Постоянный грант.
     *
     * @return bool Значение.
     */
    public function isPermanent(): bool
    {
        return $this->permanent;
    }

    /**
     * Держит ли слой внутренний урон.
     *
     * @return bool Значение.
     */
    public function isProtectsInternal(): bool
    {
        return $this->protectsInternal;
    }
}
