<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\MagicPath;

use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;

/**
 * Spec магического пути.
 */
final class MagicPathSpec implements RuleSpec
{
    /**
     * Создаёт spec.
     *
     * @param string|null $checkCode Проверка пути.
     * @param string|null $castCheckCode Проверка сотворения.
     * @param string|null $powerCharacteristicCode Мощь.
     * @param string|null $controlCharacteristicCode Контроль.
     * @param MagicPathStudyCost|null $studyCost Цена изучения.
     * @param array<int, string> $includesPathCodes Включённые пути.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $checkCode,
        private readonly ?string $castCheckCode,
        private readonly ?string $powerCharacteristicCode,
        private readonly ?string $controlCharacteristicCode,
        private readonly ?MagicPathStudyCost $studyCost,
        private readonly array $includesPathCodes,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'magic_path';
    }

    /**
     * Проверка пути.
     *
     * @return string|null Код или null.
     */
    public function getCheckCode(): ?string
    {
        return $this->checkCode;
    }

    /**
     * Проверка сотворения.
     *
     * @return string|null Код или null.
     */
    public function getCastCheckCode(): ?string
    {
        return $this->castCheckCode;
    }

    /**
     * Характеристика мощи.
     *
     * @return string|null Код или null.
     */
    public function getPowerCharacteristicCode(): ?string
    {
        return $this->powerCharacteristicCode;
    }

    /**
     * Характеристика контроля.
     *
     * @return string|null Код или null.
     */
    public function getControlCharacteristicCode(): ?string
    {
        return $this->controlCharacteristicCode;
    }

    /**
     * Цена изучения.
     *
     * @return MagicPathStudyCost|null Цена или null.
     */
    public function getStudyCost(): ?MagicPathStudyCost
    {
        return $this->studyCost;
    }

    /**
     * Пути, которые этот путь считает своими.
     *
     * @return array<int, string> Коды.
     */
    public function getIncludesPathCodes(): array
    {
        return $this->includesPathCodes;
    }
}
