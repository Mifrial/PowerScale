<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Action;

use Mifrial\Core\Auth\Service\AuthService;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Вход: loginOrEmail и пароль.
 */
final class LoginAction implements IActionHandler
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
     * Выполняет вход.
     *
     * @param string $loginOrEmail Идентификатор.
     * @param string $password Пароль.
     * @param bool $remember Долгий TTL.
     *
     * @return array{user: array<string, mixed>} User.
     */
    public function handle(string $loginOrEmail, string $password, bool $remember = false): array
    {
        return $this->authService->login($loginOrEmail, $password, $remember);
    }
}
