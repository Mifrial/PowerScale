<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;

/**
 * Модификатор состояния.
 */
final class StateModifyGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $stateCode Поле.
     * @param string $sourceCode Поле.
     * @param ScalarFormula $amount Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $stateCode,
        private readonly string $sourceCode,
        private readonly ScalarFormula $amount,
        private readonly bool $permanent,
    ) {
    }

    /**
     * Тип гранта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'state_modify';
    }

    /**
     * Код.
     *
     * @return string Значение.
     */
    public function getStateCode(): string
    {
        return $this->stateCode;
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
     * @return ScalarFormula Значение.
     */
    public function getAmount(): ScalarFormula
    {
        return $this->amount;
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
}
