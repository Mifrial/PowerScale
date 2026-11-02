<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Изменение счётчика сессии: прибавка или установка.
 */
final class CounterOp
{
    /**
     * Создаёт операцию.
     *
     * @param string $counterCode Счётчик.
     * @param int|null $delta Прибавка.
     * @param int|null $set Установка.
     *
     * @return void
     */
    private function __construct(
        private readonly string $counterCode,
        private readonly ?int $delta,
        private readonly ?int $set,
    ) {
    }

    /**
     * Прибавка.
     *
     * @param string $counterCode Счётчик.
     * @param int $delta Дельта, может быть отрицательной.
     *
     * @return self Операция.
     */
    public static function delta(string $counterCode, int $delta): self
    {
        return new self($counterCode, $delta, null);
    }

    /**
     * Установка.
     *
     * @param string $counterCode Счётчик.
     * @param int $set Новое значение.
     *
     * @return self Операция.
     */
    public static function set(string $counterCode, int $set): self
    {
        return new self($counterCode, null, $set);
    }

    /**
     * Счётчик.
     *
     * @return string Код.
     */
    public function getCounterCode(): string
    {
        return $this->counterCode;
    }

    /**
     * Установка ли это.
     *
     * @return bool true для set.
     */
    public function isSet(): bool
    {
        return $this->set !== null;
    }

    /**
     * Новое значение счётчика.
     *
     * @param int $current Текущее значение.
     *
     * @return int Новое значение.
     */
    public function apply(int $current): int
    {
        return $this->set ?? $current + ($this->delta ?? 0);
    }
}
