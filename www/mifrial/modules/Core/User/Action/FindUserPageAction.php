<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Dto\Action\FindPageInput;
use Mifrial\Core\User\Service\UserHttpService;

/**
 * Страница учёток.
 */
final class FindUserPageAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param UserHttpService $userHttpService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly UserHttpService $userHttpService,
    ) {
    }

    /**
     * Возвращает items и total.
     *
     * @param FindPageInput $input Страница.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     */
    public function handle(FindPageInput $input): array
    {
        return $this->userHttpService->findPage($input);
    }
}
