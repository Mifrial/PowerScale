<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;

/**
 * Собирает независимый от соседних модулей EventManager.
 */
final class EventPortFactory
{
    /**
     * Создаёт EventManager.
     *
     * @return IEventManager Синхронный EventManager.
     */
    public function create(): IEventManager
    {
        return new EventManager();
    }
}
