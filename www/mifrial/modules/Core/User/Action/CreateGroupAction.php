<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Dto\Action\CreateGroupInput;
use Mifrial\Core\User\Service\GroupHttpService;

/**
 * Создание группы.
 */
final class CreateGroupAction implements IActionHandler
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
     * Пишет группу.
     *
     * @param CreateGroupInput $input JSON create.
     *
     * @return array<string, mixed> Group.
     */
    public function handle(CreateGroupInput $input): array
    {
        return $this->groupHttpService->create($input);
    }
}
