<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Service\GroupHttpService;

/**
 * Одна группа по id.
 */
final class GetGroupAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GroupHttpService $groupHttpService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GroupHttpService $groupHttpService,
    ) {
    }

    /**
     * Возвращает Group.
     *
     * @param int $id Id.
     *
     * @return array<string, mixed> Group.
     */
    public function handle(int $id): array
    {
        return $this->groupHttpService->get($id);
    }
}
