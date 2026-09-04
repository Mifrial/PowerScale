<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Action;

use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Service\AuthService;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Гостевая сессия без учётки.
 */
final class GuestAction implements IActionHandler
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
     * Открывает guest-сессию.
     *
     * @return array{kind: string} Конверт.
     *
     * @throws AuthInvalidException Если уже user-сессия.
     */
    public function handle(): array
    {
        return $this->authService->openGuest();
    }
}
