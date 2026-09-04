<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Action;

use Mifrial\Core\Auth\Dto\Action\SetPasswordInput;
use Mifrial\Core\Auth\Service\SetPasswordService;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Смена пароля аутентифицированным актором.
 */
final class SetPasswordAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param SetPasswordService $setPasswordService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly SetPasswordService $setPasswordService,
    ) {
    }

    /**
     * Меняет пароль цели.
     *
     * @param SetPasswordInput $input JSON.
     *
     * @return true Успех.
     */
    public function handle(SetPasswordInput $input): bool
    {
        return $this->setPasswordService->setPassword(
            $input->userId,
            $input->newPassword,
            $input->currentPassword,
        );
    }
}
