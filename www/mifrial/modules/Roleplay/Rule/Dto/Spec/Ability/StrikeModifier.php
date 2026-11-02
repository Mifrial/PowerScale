<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Пакет удара, который даёт шаг процесса или карточка-потомок.
 */
final class StrikeModifier
{
    /**
     * Создаёт пакет.
     *
     * @param ProcessFormula|null $advantage Помеха или преимущество попадания.
     * @param ProcessFormula|null $extraSuccesses Прибавка к SR урона.
     *
     * @return void
     */
    public function __construct(
        private readonly ?ProcessFormula $advantage = null,
        private readonly ?ProcessFormula $extraSuccesses = null,
    ) {
    }

    /**
     * Помеха или преимущество.
     *
     * @return ProcessFormula|null Формула или null.
     */
    public function getAdvantage(): ?ProcessFormula
    {
        return $this->advantage;
    }

    /**
     * Прибавка к SR урона.
     *
     * @return ProcessFormula|null Формула или null.
     */
    public function getExtraSuccesses(): ?ProcessFormula
    {
        return $this->extraSuccesses;
    }

    /**
     * Есть ли хоть один модификатор.
     *
     * @return bool true, если пакет пуст.
     */
    public function isEmpty(): bool
    {
        return $this->advantage === null && $this->extraSuccesses === null;
    }

    /**
     * Значение преимущества по счётчикам.
     *
     * @param array<string, mixed> $counters Счётчики сессии.
     *
     * @return int Дельта, 0 без формулы.
     */
    public function advantageOf(array $counters): int
    {
        return $this->advantage === null ? 0 : $this->advantage->evaluate($counters);
    }

    /**
     * Значение прибавки к SR по счётчикам.
     *
     * @param array<string, mixed> $counters Счётчики сессии.
     *
     * @return int Прибавка, 0 без формулы.
     */
    public function extraSuccessesOf(array $counters): int
    {
        return $this->extraSuccesses === null ? 0 : $this->extraSuccesses->evaluate($counters);
    }
}
