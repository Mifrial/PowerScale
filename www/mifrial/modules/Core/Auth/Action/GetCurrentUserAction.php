<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Action;

use Mifrial\Core\Auth\Service\AuthService;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Текущий пользователь по cookie.
 */
final class GetCurrentUserAction implements IActionHandler
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
     * Возвращает User или null.
     *
     * @return array<string, mixed>|null Сборка или нет сессии.
     */
    public function handle(): ?array
    {
        return $this->authService->currentUser();
    }
}
