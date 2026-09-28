<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Interface\Service;

use Mifrial\Core\Event\Interface\Value\IEventPayload;
use Mifrial\Core\Event\Interface\Value\IEventResult;

/**
 * Контракт синхронного listener runtime-события.
 */
interface IEventListener
{
    /**
     * Обрабатывает payload события.
     *
     * @param IEventPayload $payload Входной payload.
     *
     * @return IEventResult|null Локальный результат или null для успеха.
     */
    public function handle(IEventPayload $payload): ?IEventResult;
}
