<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Mechanic\Dto\Action\CreateMechanicInput;
use Mifrial\Roleplay\Mechanic\Service\MechanicHttpService;

/**
 * Создание поставки механики.
 */
final class CreateMechanicAction implements IActionHandler
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
     * @param CreateMechanicInput $input JSON.
     *
     * @return array<string, mixed> Поставка.
     */
    public function handle(CreateMechanicInput $input): array
    {
        return $this->mechanicHttpService->create($input);
    }
}
