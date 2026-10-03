<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;

/**
 * Модификатор характеристики.
 */
final class CharacteristicModifyGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $characteristicCode Поле.
     * @param string $sourceCode Поле.
     * @param array $checkCodes Поле.
     * @param ScalarFormula $amount Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly string $sourceCode,
        private readonly array $checkCodes,
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
        return 'characteristic_modify';
    }

    /**
     * Код.
     *
     * @return string Значение.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
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
     * Коды проверок.
     *
     * @return array Значение.
     */
    public function getCheckCodes(): array
    {
        return $this->checkCodes;
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
