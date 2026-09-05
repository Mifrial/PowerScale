<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Mechanic\Service\MechanicHttpService;

/**
 * Поставка механики по id.
 */
final class GetMechanicAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param MechanicHttpService $mechanicHttpService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly MechanicHttpService $mechanicHttpService,
    ) {
    }

    /**
     * Возвращает Mechanic.
     *
     * @param int $id Mechanic id.
     *
     * @return array<string, mixed> Поставка.
     */
    public function handle(int $id): array
    {
        return $this->mechanicHttpService->get($id);
    }
}
