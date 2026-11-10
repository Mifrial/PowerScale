<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Scene\Dto\Action\SceneIdInput;
use Mifrial\Roleplay\Scene\Service\SceneCatalogHttp;

/**
 * HTTP-обработчик локации.
 */
final class GetSceneAction implements IActionHandler
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
     * @param SceneIdInput $input JSON.
     *
     * @return array<string, mixed> Результат.
     */
    public function handle(SceneIdInput $input): array
    {
        return $this->service->get($input);
    }
}
