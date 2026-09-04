<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Action;

use Mifrial\Core\Auth\Dto\Action\UserCreateInput;
use Mifrial\Core\Auth\Service\UserCreateService;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Создаёт учётку с паролем без новой сессии.
 */
final class UserCreateAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param UserCreateService $userCreateService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly UserCreateService $userCreateService,
    ) {
    }

    /**
     * Создаёт учётку.
     *
     * @param UserCreateInput $input JSON create.
     *
     * @return array<string, mixed> User.
     */
    public function handle(UserCreateInput $input): array
    {
        return $this->userCreateService->create($input);
    }
}
