<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Длительность заклинания.
 */
final class SpellDuration
{
    /**
     * Создаёт длительность.
     *
     * @param string $type instant, lingering, refreshable или sustained.
     * @param int|DimensionalNumber|null $limit Лимит.
     * @param string|null $unit Единица лимита.
     * @param int|DimensionalNumber|null $actionCost Цена обновления.
     * @param SpellValue|null $power Мощь поддержания.
     *
     * @return void
     */
    public function __construct(
        private readonly string $type,
        private readonly int|DimensionalNumber|null $limit,
        private readonly ?string $unit,
        private readonly int|DimensionalNumber|null $actionCost,
        private readonly ?SpellValue $power,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Лимит.
     *
     * @return int|DimensionalNumber|null Значение или null.
     */
    public function getLimit(): int|DimensionalNumber|null
    {
        return $this->limit;
    }

    /**
     * Единица лимита.
     *
     * @return string|null Код или null.
     */
    public function getUnit(): ?string
    {
        return $this->unit;
    }

    /**
     * Цена обновления.
     *
     * @return int|DimensionalNumber|null Значение или null.
     */
    public function getActionCost(): int|DimensionalNumber|null
    {
        return $this->actionCost;
    }

    /**
     * Мощь поддержания.
     *
     * @return SpellValue|null Значение или null.
     */
    public function getPower(): ?SpellValue
    {
        return $this->power;
    }
}
