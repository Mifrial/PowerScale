<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Service\UserHttpService;

/**
 * Одна учётка по id.
 */
final class GetUserAction implements IActionHandler
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
     * Возвращает User.
     *
     * @param int $id Id.
     *
     * @return array<string, mixed> User.
     */
    public function handle(int $id): array
    {
        return $this->userHttpService->get($id);
    }
}
