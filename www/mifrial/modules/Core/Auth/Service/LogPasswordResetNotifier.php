<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Auth\Interface\Service\IPasswordResetNotifier;

/**
 * Пишет reset-токен в error_log; expose — из local.php.
 */
final class LogPasswordResetNotifier implements IPasswordResetNotifier
{
    /**
     * Создаёт notifier.
     *
     * @param AuthSettings $authSettings Срез auth.
     *
     * @return void
     */
    public function __construct(
        private readonly AuthSettings $authSettings,
    ) {
    }

    /**
     * Пишет логин и сырой токен в error_log.
     *
     * @param string $login Логин учётки.
     * @param string $rawToken Сырой токен.
     * @param string $email Почта.
     *
     * @return void
     */
    public function notify(string $login, string $rawToken, string $email): void
    {
        error_log(
            'auth.startPasswordReset login=' . $login . ' email=' . $email . ' token=' . $rawToken,
        );
    }

    /**
     * Нужно ли отдать сырой токен в JSON.
     *
     * @return bool true, если expose.
     */
    public function shouldExposeRawToken(): bool
    {
        return $this->authSettings->exposeResetToken();
    }
}
