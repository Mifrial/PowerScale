<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Action;

use Mifrial\Core\File\Service\FileHttpService;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Файл по id.
 */
final class GetFileAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param FileHttpService $fileHttpService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly FileHttpService $fileHttpService,
    ) {
    }

    /**
     * Возвращает FileEntry.
     *
     * @param int $id Идентификатор.
     *
     * @return array<string, mixed> Запись.
     */
    public function handle(int $id): array
    {
        return $this->fileHttpService->get($id);
    }
}
