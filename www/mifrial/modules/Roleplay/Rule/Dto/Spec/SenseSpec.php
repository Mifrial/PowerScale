<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Spec чувства.
 */
final class SenseSpec implements RuleSpec
{
    /**
     * Создаёт значение.
     *
     * @param string $status Статус.
     * @param DimensionalNumber $radius Радиус.
     *
     * @return void
     */
    public function __construct(
        private readonly string $status,
        private readonly DimensionalNumber $radius,
        private readonly bool $autoAdd,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'sense';
    }

    /**
     * Статус.
     *
     * @return string Значение.
     */
    public function getStatus(): string
    {
        return $this->status;
    }
    /**
     * Радиус.
     *
     * @return DimensionalNumber Значение.
     */
    public function getRadius(): DimensionalNumber
    {
        return $this->radius;
    }

    /**
     * Чувство есть у каждого персонажа с бонусом 0, пока грант его не изменит.
     *
     * @return bool Значение.
     */
    public function isAutoAdd(): bool
    {
        return $this->autoAdd;
    }
}
