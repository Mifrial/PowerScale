<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

/**
 * Результат чтения слота кэша: попадание или промах.
 */
final class CacheHit
{
    /**
     * Создаёт слот чтения.
     *
     * @param bool $found True, если ключ найден и не истёк.
     * @param mixed $value Payload или null.
     *
     * @return void
     */
    public function __construct(
        private readonly bool $found,
        private readonly mixed $value,
    ) {
    }

    /**
     * Был ли ключ в store.
     *
     * @return bool true при попадании, в том числе cached null.
     */
    public function found(): bool
    {
        return $this->found;
    }

    /**
     * Возвращает значение слота.
     *
     * @return mixed Payload.
     */
    public function value(): mixed
    {
        return $this->value;
    }
}
