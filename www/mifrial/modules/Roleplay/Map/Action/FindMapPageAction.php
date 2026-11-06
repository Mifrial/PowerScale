<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Map\Dto\Action\FindMapPageInput;
use Mifrial\Roleplay\Map\Service\MapCatalogHttp;

/**
 * HTTP-обработчик карт.
 */
final class FindMapPageAction implements IActionHandler
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
     * @param FindMapPageInput $input JSON.
     *
     * @return array<string, mixed> Результат.
     */
    public function handle(FindMapPageInput $input): array
    {
        return $this->service->findPage($input);
    }
}
