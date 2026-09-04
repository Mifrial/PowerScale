<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Action;

use Mifrial\Core\Auth\Dto\Action\FinalPasswordResetInput;
use Mifrial\Core\Auth\Service\PasswordResetService;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Завершение сброса пароля.
 */
final class FinalPasswordResetAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param PasswordResetService $passwordResetService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {
    }

    /**
     * Меняет пароль по токену.
     *
     * @param FinalPasswordResetInput $input JSON.
     *
     * @return true Успех.
     */
    public function handle(FinalPasswordResetInput $input): bool
    {
        return $this->passwordResetService->finalPasswordReset(
            $input->login,
            $input->resetToken,
            $input->newPassword,
        );
    }
}
