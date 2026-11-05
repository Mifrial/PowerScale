<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Action;

use Mifrial\Core\File\Dto\Action\FindOwnImagesInput;
use Mifrial\Core\File\Service\FileHttpService;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Страница картинок текущего актора.
 */
final class FindOwnImagesAction implements IActionHandler
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
     * Возвращает items и total.
     *
     * @param FindOwnImagesInput $input Страница.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     */
    public function handle(FindOwnImagesInput $input): array
    {
        return $this->fileHttpService->findOwnImages($input);
    }
}
