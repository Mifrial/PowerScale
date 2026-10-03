<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

/**
 * Ветка resource_limit_modify.
 */
final class ResourceLimitModifyEffect implements StateEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $resourceCode Ресурс.
     * @param int $amount Величина.
     * @param bool $perUnit На единицу.
     *
     * @return void
     */
    public function __construct(
        private readonly string $resourceCode,
        private readonly int $amount,
        private readonly bool $perUnit,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'resource_limit_modify';
    }

    /**
     * Ресурс.
     *
     * @return string Значение.
     */
    public function getResourceCode(): string
    {
        return $this->resourceCode;
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
}
