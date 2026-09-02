<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Action;

use Mifrial\Core\Auth\Service\AuthService;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Открытая регистрация.
 */
final class RegisterAction implements IActionHandler
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
     * Регистрирует учётку.
     *
     * @param string $login Логин.
     * @param string $email Почта.
     * @param string $password Пароль.
     *
     * @return array{user: array<string, mixed>} User.
     */
    public function handle(string $login, string $email, string $password): array
    {
        return $this->authService->register($login, $email, $password);
    }
}
