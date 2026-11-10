<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Scene\Dto\Action\FindScenePageInput;
use Mifrial\Roleplay\Scene\Service\SceneCatalogHttp;

/**
 * HTTP-обработчик каталога локаций.
 */
final class FindScenePageAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param SceneCatalogHttp $service Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneCatalogHttp $service,
    ) {
    }

    /**
     * Выполняет сценарий.
     *
     * @param FindScenePageInput $input JSON.
     *
     * @return array<string, mixed> Результат.
     */
    public function handle(FindScenePageInput $input): array
    {
        return $this->service->findPage($input);
    }
}
