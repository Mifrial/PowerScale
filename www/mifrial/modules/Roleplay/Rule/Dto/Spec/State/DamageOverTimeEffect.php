<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

/**
 * Ветка damage_over_time.
 */
final class DamageOverTimeEffect implements StateEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $damageKind Вид урона.
     * @param int $amount Фиксированный урон.
     * @param ?string $damageTypeCode Тип урона.
     * @param ?int $periodValue Период.
     * @param ?string $periodStep Шаг периода.
     * @param ?StateDecay $decay Затухание.
     *
     * @return void
     */
    public function __construct(
        private readonly string $damageKind,
        private readonly int $amount,
        private readonly ?string $damageTypeCode,
        private readonly ?int $periodValue,
        private readonly ?string $periodStep,
        private readonly ?StateDecay $decay,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'damage_over_time';
    }

    /**
     * Вид урона.
     *
     * @return string Значение.
     */
    public function getDamageKind(): string
    {
        return $this->damageKind;
    }

    /**
     * Фиксированный урон.
     *
     * @return int Значение.
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * Тип урона.
     *
     * @return ?string Значение.
     */
    public function getDamageTypeCode(): ?string
    {
        return $this->damageTypeCode;
    }

    /**
     * Период.
     *
     * @return ?int Значение.
     */
    public function getPeriodValue(): ?int
    {
        return $this->periodValue;
    }

    /**
     * Шаг периода.
     *
     * @return ?string Значение.
     */
    public function getPeriodStep(): ?string
    {
        return $this->periodStep;
    }

    /**
     * Затухание.
     *
     * @return ?StateDecay Значение.
     */
    public function getDecay(): ?StateDecay
    {
        return $this->decay;
    }
}
