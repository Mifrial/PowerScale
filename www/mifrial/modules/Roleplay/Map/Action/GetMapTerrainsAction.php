<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Map\Service\MapTerrainHttp;

/**
 * HTTP-обработчик карт.
 */
final class GetMapTerrainsAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param MapTerrainHttp $service Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly MapTerrainHttp $service,
    ) {
    }

    /**
     * Выполняет сценарий.
     *
     * @return array<int, array<string, mixed>> Результат.
     */
    public function handle(): array
    {
        return $this->service->getTerrains();
    }
}
