<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Map\Dto\Action\MapIdInput;
use Mifrial\Roleplay\Map\Service\MapCatalogHttp;

/**
 * HTTP-обработчик карт.
 */
final class GetMapAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param MapCatalogHttp $service Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly MapCatalogHttp $service,
    ) {
    }

    /**
     * Выполняет сценарий.
     *
     * @param MapIdInput $input JSON.
     *
     * @return array<string, mixed> Результат.
     */
    public function handle(MapIdInput $input): array
    {
        return $this->service->get($input);
    }
}
