<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Interface\Value;

use Mifrial\Core\Event\Exception\EventException;

/**
 * Контракт неизменяемого payload runtime-события.
 */
interface IEventPayload
{
    /**
     * Проверяет наличие ключа в payload.
     *
     * @param string $key Ключ payload.
     *
     * @return bool true, если ключ существует.
     */
    public function has(string $key): bool;

    /**
     * Возвращает значение payload.
     *
     * @param string $key Ключ payload.
     *
     * @return mixed Значение payload.
     *
     * @throws EventException Если ключ отсутствует.
     */
    public function get(string $key): mixed;
}
