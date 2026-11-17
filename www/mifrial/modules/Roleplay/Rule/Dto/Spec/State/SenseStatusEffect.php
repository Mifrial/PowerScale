<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

/**
 * Ветка sense_status: состояние меняет статус чувства.
 */
final class SenseStatusEffect implements StateEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $senseCode Код чувства.
     * @param string $status Статус: precise, imprecise, vague или absent.
     *
     * @return void
     */
    public function __construct(
        private readonly string $senseCode,
        private readonly string $status,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'sense_status';
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
     * Статус чувства.
     *
     * @return string Значение.
     */
    public function getStatus(): string
    {
        return $this->status;
    }
}
