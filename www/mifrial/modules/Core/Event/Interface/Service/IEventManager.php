<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Interface\Service;

use Mifrial\Core\Event\Interface\Value\IEventPayload;
use Mifrial\Core\Event\Interface\Value\IEventResult;
use Mifrial\Core\Event\Value\EventSubscription;

/**
 * Публичный порт синхронной runtime-доставки событий.
 */
interface IEventManager
{
    /**
     * Регистрирует listener события.
     *
     * @param string $eventName Canonical имя события.
     * @param IEventListener $listener Listener.
     * @param int $priority Приоритет, меньшее значение выполняется раньше.
     *
     * @return EventSubscription Непрозрачный token подписки.
     */
    public function on(
        string $eventName,
        IEventListener $listener,
        int $priority = 0,
    ): EventSubscription;

    /**
     * Снимает одну подписку.
     *
     * @param EventSubscription $subscription Token подписки.
     *
     * @return void
     */
    public function off(EventSubscription $subscription): void;

    /**
     * Синхронно доставляет событие listeners.
     *
     * @param string $eventName Canonical имя события.
     * @param IEventPayload $payload Неизменяемый payload.
     *
     * @return IEventResult Агрегированный результат dispatch.
     */
    public function fire(string $eventName, IEventPayload $payload): IEventResult;
}
