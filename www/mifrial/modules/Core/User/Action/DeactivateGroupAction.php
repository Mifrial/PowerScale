<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Service\GroupHttpService;

/**
 * Деактивация группы.
 */
final class DeactivateGroupAction implements IActionHandler
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
     * Снимает active.
     *
     * @param int $id Id.
     *
     * @return null Успех.
     */
    public function handle(int $id): mixed
    {
        return $this->groupHttpService->deactivate($id);
    }
}
