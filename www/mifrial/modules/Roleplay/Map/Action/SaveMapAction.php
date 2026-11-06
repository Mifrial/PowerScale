<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Map\Dto\Action\SaveMapInput;
use Mifrial\Roleplay\Map\Service\MapSaveHttp;

/**
 * HTTP-обработчик публикации карты.
 */
final class SaveMapAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param MapSaveHttp $service Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly MapSaveHttp $service,
    ) {
    }

    /**
     * Выполняет сценарий.
     *
     * @param SaveMapInput $input JSON.
     *
     * @return array<string, mixed> Результат.
     */
    public function handle(SaveMapInput $input): array
    {
        return $this->service->save($input);
    }
}
