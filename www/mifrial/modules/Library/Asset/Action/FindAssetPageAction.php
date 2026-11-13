<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Action;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Library\Asset\Dto\Action\FindAssetPageInput;
use Mifrial\Library\Asset\Service\AssetHttpService;

/**
 * HTTP-обработчик страницы ассетов.
 */
final class FindAssetPageAction implements IActionHandler
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
     * @param FindAssetPageInput $input JSON.
     *
     * @return array<string, mixed> Результат.
     *
     * @throws ActionException Если актора нет или запрос недопустим.
     */
    public function handle(FindAssetPageInput $input): array
    {
        return $this->service->findPage($input);
    }
}
