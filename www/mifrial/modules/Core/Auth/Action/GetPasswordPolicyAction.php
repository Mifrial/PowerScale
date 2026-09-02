<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Action;

use Mifrial\Core\Auth\Service\AuthService;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Политика пароля регистрации.
 */
final class GetPasswordPolicyAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param AuthService $authService Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    /**
     * Возвращает политику v1.
     *
     * @return array{minLength: int, requireMixedCase: bool, requireDigit: bool, requireSpecialChar: bool}
     */
    public function handle(): array
    {
        return $this->authService->passwordPolicy();
    }
}
