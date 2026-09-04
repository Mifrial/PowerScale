<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Action;

use Mifrial\Core\Auth\Dto\Action\GetPasswordPolicyInput;
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
     * Возвращает default или effective политику.
     *
     * @param GetPasswordPolicyInput $input JSON.
     *
     * @return array{minLength: int, requireMixedCase: bool, requireDigit: bool, requireSpecialChar: bool}
     */
    public function handle(GetPasswordPolicyInput $input): array
    {
        return $this->authService->getPasswordPolicy($input->userId);
    }
}
