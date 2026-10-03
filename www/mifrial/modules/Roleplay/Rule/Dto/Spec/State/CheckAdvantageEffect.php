<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

/**
 * Ветка check_advantage.
 */
final class CheckAdvantageEffect implements StateEffect
{
    /**
     * Создаёт ветку.
     *
     * @param int $amount Величина.
     * @param bool $perUnit На единицу.
     * @param ?string $scale Масштаб.
     * @param bool $includesHit Попадание.
     * @param array $characteristicCodes Характеристики.
     * @param array $checkCodes Проверки.
     * @param ?string $sourceCode Источник.
     * @param ?int $maxAbs Потолок модуля.
     *
     * @return void
     */
    public function __construct(
        private readonly int $amount,
        private readonly bool $perUnit,
        private readonly ?string $scale,
        private readonly bool $includesHit,
        private readonly array $characteristicCodes,
        private readonly array $checkCodes,
        private readonly ?string $sourceCode,
        private readonly ?int $maxAbs,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'check_advantage';
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
     * На единицу.
     *
     * @return bool Значение.
     */
    public function isPerUnit(): bool
    {
        return $this->perUnit;
    }

    /**
     * Масштаб.
     *
     * @return ?string Значение.
     */
    public function getScale(): ?string
    {
        return $this->scale;
    }

    /**
     * Попадание.
     *
     * @return bool Значение.
     */
    public function isIncludesHit(): bool
    {
        return $this->includesHit;
    }

    /**
     * Характеристики.
     *
     * @return array Значение.
     */
    public function getCharacteristicCodes(): array
    {
        return $this->characteristicCodes;
    }

    /**
     * Проверки.
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
     * Потолок модуля.
     *
     * @return ?int Значение.
     */
    public function getMaxAbs(): ?int
    {
        return $this->maxAbs;
    }
}
