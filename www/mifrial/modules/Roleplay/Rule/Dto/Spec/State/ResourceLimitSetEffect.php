<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

/**
 * Ветка resource_limit_set.
 */
final class ResourceLimitSetEffect implements StateEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $resourceCode Ресурс.
     * @param int $value Значение.
     *
     * @return void
     */
    public function __construct(
        private readonly string $resourceCode,
        private readonly int $value,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'resource_limit_set';
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
     * Значение.
     *
     * @return int Значение.
     */
    public function getValue(): int
    {
        return $this->value;
    }
}
