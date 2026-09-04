<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\User\Dto\Action\UpdateUserInput;
use Mifrial\Core\User\Service\UserHttpService;

/**
 * Обновление профиля.
 */
final class UpdateUserAction implements IActionHandler
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
     * Пишет присутствующие поля.
     *
     * @param UpdateUserInput $input JSON update.
     *
     * @return array<string, mixed> User.
     */
    public function handle(UpdateUserInput $input): array
    {
        return $this->userHttpService->update($input);
    }
}
