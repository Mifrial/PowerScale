<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Эффективность проверки.
 */
final class CheckEfficiencyGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param int $amount Поле.
     * @param array $checkCodes Поле.
     * @param ?string $sourceCode Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly int $amount,
        private readonly array $checkCodes,
        private readonly ?string $sourceCode,
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
        return 'check_efficiency';
    }

    /**
     * Величина.
     *
     * @return int Значение.
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * Коды.
     *
     * @return array Значение.
     */
    public function getCheckCodes(): array
    {
        return $this->checkCodes;
    }

    /**
     * Источник.
     *
     * @return ?string Значение.
     */
    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
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
