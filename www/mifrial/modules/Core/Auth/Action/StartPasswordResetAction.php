<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Action;

use Mifrial\Core\Auth\Dto\Action\StartPasswordResetInput;
use Mifrial\Core\Auth\Service\PasswordResetService;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Старт сброса пароля.
 */
final class StartPasswordResetAction implements IActionHandler
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
     * Ищет учётку и выпускает токен.
     *
     * @param StartPasswordResetInput $input JSON.
     *
     * @return array{status: string, login?: string, resetToken?: string} Исход.
     */
    public function handle(StartPasswordResetInput $input): array
    {
        return $this->passwordResetService->startPasswordReset($input->loginOrEmail);
    }
}
