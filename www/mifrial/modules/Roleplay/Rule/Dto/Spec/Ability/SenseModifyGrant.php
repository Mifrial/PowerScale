<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;

/**
 * Модификатор чувства.
 */
final class SenseModifyGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $senseCode Поле.
     * @param string $sourceCode Поле.
     * @param ?string $status Поле.
     * @param ?string $treatAsGoodDownTo Поле.
     * @param ScalarFormula $amount Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $senseCode,
        private readonly string $sourceCode,
        private readonly ?string $status,
        private readonly ?string $treatAsGoodDownTo,
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
        return 'sense_modify';
    }

    /**
     * Код чувства.
     *
     * @return string Значение.
     */
    public function getSenseCode(): string
    {
        return $this->senseCode;
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
     * Статус.
     *
     * @return ?string Значение.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Освещение.
     *
     * @return ?string Значение.
     */
    public function getTreatAsGoodDownTo(): ?string
    {
        return $this->treatAsGoodDownTo;
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
