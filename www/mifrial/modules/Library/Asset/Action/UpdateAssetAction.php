<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Action;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Library\Asset\Dto\Action\UpdateAssetInput;
use Mifrial\Library\Asset\Service\AssetHttpService;

/**
 * HTTP-обработчик переименования ассета.
 */
final class UpdateAssetAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param AssetHttpService $service Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly AssetHttpService $service,
    ) {
    }

    /**
     * Выполняет сценарий.
     *
     * @param UpdateAssetInput $input JSON.
     *
     * @return array<string, mixed> Обновлённый ассет.
     *
     * @throws ActionException Если актора нет, правка запрещена или имя недопустимо.
     */
    public function handle(UpdateAssetInput $input): array
    {
        return $this->service->update($input);
    }
}
