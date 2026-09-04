<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Value\Optional;

use Mifrial\Core\Kernel\Exception\KernelException;

/**
 * Поле JSON с признаком «ключ был». Листья задают тип значения.
 */
abstract class OptionalValue
{
    /**
     * Запоминает, был ли ключ.
     *
     * @param bool $isPresent Ключ был в JSON.
     *
     * @return void
     */
    protected function __construct(
        private readonly bool $isPresent,
    ) {
    }

    /**
     * Ключа нет.
     *
     * @return static Absent.
     */
    abstract public static function absent(): static;

    /**
     * Собирает present из JSON-значения.
     *
     * @param mixed $jsonValue Значение ключа.
     *
     * @return static Present.
     *
     * @throws KernelException Если JSON не подходит листу.
     */
    abstract public static function fromJson(mixed $jsonValue): static;

    /**
     * Был ли ключ в JSON.
     *
     * @return bool true, если ключ был.
     */
    public function isPresent(): bool
    {
        return $this->isPresent;
    }

    /**
     * Отвергает чтение, если ключа не было.
     *
     * @return void
     *
     * @throws KernelException OPTIONAL_ABSENT.
     */
    protected function assertPresent(): void
    {
        if (!$this->isPresent) {
            throw new KernelException('OPTIONAL_ABSENT', 'Optional value was not present');
        }
    }

    /**
     * Отвергает JSON, который лист не ест.
     *
     * @return never
     *
     * @throws KernelException OPTIONAL_JSON.
     */
    protected static function rejectJson(): never
    {
        throw new KernelException('OPTIONAL_JSON', 'Optional JSON value is invalid');
    }
}
