<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Базовый лимит ресурса.
 */
final class ResourceLimit
{
    /**
     * Создаёт лимит.
     *
     * @param int|DimensionalNumber $base Старт.
     * @param array<int, ResourceAdjustment> $adjustments Поправки.
     *
     * @return void
     */
    public function __construct(
        private readonly int|DimensionalNumber $base,
        private readonly array $adjustments,
    ) {
    }

    /**
     * Стартовое значение.
     *
     * @return int|DimensionalNumber Число.
     */
    public function getBase(): int|DimensionalNumber
    {
        return $this->base;
    }

    /**
     * Поправки.
     *
     * @return array<int, ResourceAdjustment> Список.
     */
    public function getAdjustments(): array
    {
        return $this->adjustments;
    }
}
