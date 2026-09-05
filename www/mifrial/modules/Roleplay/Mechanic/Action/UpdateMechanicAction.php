<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Mechanic\Dto\Action\UpdateMechanicInput;
use Mifrial\Roleplay\Mechanic\Service\MechanicHttpService;

/**
 * Обновление подписи механики.
 */
final class UpdateMechanicAction implements IActionHandler
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
     * @param UpdateMechanicInput $input JSON.
     *
     * @return array<string, mixed> Поставка.
     */
    public function handle(UpdateMechanicInput $input): array
    {
        return $this->mechanicHttpService->update($input);
    }
}
