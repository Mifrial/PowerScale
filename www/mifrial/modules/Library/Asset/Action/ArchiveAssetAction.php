<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Action;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Library\Asset\Dto\Action\ArchiveAssetInput;
use Mifrial\Library\Asset\Service\AssetHttpService;

/**
 * HTTP-обработчик архива ассета.
 */
final class ArchiveAssetAction implements IActionHandler
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
     * @param ArchiveAssetInput $input JSON.
     *
     * @return array<string, mixed> Пустой результат.
     *
     * @throws ActionException Если актора нет или архив запрещён.
     */
    public function handle(ArchiveAssetInput $input): array
    {
        return $this->service->archive($input);
    }
}
