<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Dto\Action\GetGroupMembersInput;
use Mifrial\Core\User\Service\GroupHttpService;

/**
 * Страница членов группы.
 */
final class GetGroupMembersAction implements IActionHandler
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
     * Возвращает items и total.
     *
     * @param GetGroupMembersInput $input Страница.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     */
    public function handle(GetGroupMembersInput $input): array
    {
        return $this->groupHttpService->getMembers($input);
    }
}
