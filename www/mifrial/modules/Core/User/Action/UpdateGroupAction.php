<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Dto\Action\UpdateGroupInput;
use Mifrial\Core\User\Service\GroupHttpService;

/**
 * Обновление группы.
 */
final class UpdateGroupAction implements IActionHandler
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
     * Пишет присутствующие поля.
     *
     * @param UpdateGroupInput $input JSON update.
     *
     * @return array<string, mixed> Group.
     */
    public function handle(UpdateGroupInput $input): array
    {
        return $this->groupHttpService->update($input);
    }
}
