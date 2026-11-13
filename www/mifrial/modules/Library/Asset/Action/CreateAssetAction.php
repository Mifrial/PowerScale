<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Action;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Library\Asset\Dto\Action\CreateAssetInput;
use Mifrial\Library\Asset\Service\AssetHttpService;

/**
 * HTTP-обработчик создания ассета.
 */
final class CreateAssetAction implements IActionHandler
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
     * @param CreateAssetInput $input JSON.
     *
     * @return array<string, mixed> Созданный ассет.
     *
     * @throws ActionException Если актора нет, правка запрещена или файл недопустим.
     */
    public function handle(CreateAssetInput $input): array
    {
        return $this->service->create($input);
    }
}
