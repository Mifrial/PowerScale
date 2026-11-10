<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Scene\Dto\Action\SaveSceneInput;
use Mifrial\Roleplay\Scene\Service\SceneSaveHttp;

/**
 * HTTP-обработчик сохранения локации.
 */
final class SaveSceneAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param SceneSaveHttp $service Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneSaveHttp $service,
    ) {
    }

    /**
     * Выполняет сценарий.
     *
     * @param SaveSceneInput $input JSON.
     *
     * @return array<string, mixed> Результат.
     */
    public function handle(SaveSceneInput $input): array
    {
        return $this->service->save($input);
    }
}
