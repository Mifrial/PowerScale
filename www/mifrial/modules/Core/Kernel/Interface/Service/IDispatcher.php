<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Service;

use Mifrial\Core\Kernel\Dto\ActionResponse;

/**
 * Контракт диспетчера действий.
 */
interface IDispatcher
{
    /**
     * Находит обработчик действия и передаёт ему полезную нагрузку.
     *
     * @param string $action  Код действия.
     * @param mixed $payload Полезная нагрузка запроса.
     *
     * @return ActionResponse Ответ обработчика.
     */
    public function dispatch(string $action, mixed $payload): ActionResponse;
}
