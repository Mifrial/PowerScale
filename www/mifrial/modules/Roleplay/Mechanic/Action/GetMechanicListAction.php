<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Mechanic\Service\MechanicHttpService;

/**
 * Каталог поставок механик.
 */
final class GetMechanicListAction implements IActionHandler
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
     * Возвращает Mechanic[].
     *
     * @return array<int, array<string, mixed>> Поставки.
     */
    public function handle(): array
    {
        return $this->mechanicHttpService->getList();
    }
}
