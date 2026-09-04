<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Service\UserHttpService;

/**
 * Пачка учёток по id.
 */
final class GetUsersByIdsAction implements IActionHandler
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
     * Возвращает найденных.
     *
     * @param array<int, mixed> $ids Id.
     *
     * @return array<int, array<string, mixed>> User[].
     */
    public function handle(array $ids): array
    {
        return $this->userHttpService->getByIds($ids);
    }
}
