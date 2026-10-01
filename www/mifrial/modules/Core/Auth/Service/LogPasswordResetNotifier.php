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
    private string $pendingLogin = '';

    private string $pendingToken = '';

    private string $pendingEmail = '';

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
     * Запоминает токен до commit.
     *
     * @param string $login Логин учётки.
     * @param string $rawToken Сырой токен.
     * @param string $email Почта.
     *
     * @return int Всегда 0: очереди нет.
     */
    public function enqueue(string $login, string $rawToken, string $email): int
    {
        $this->pendingLogin = $login;
        $this->pendingToken = $rawToken;
        $this->pendingEmail = $email;

        return 0;
    }

    /**
     * Пишет отложенный токен в error_log после commit.
     *
     * @param int $jobId Id job; у лога 0.
     *
     * @return void
     */
    public function deliver(int $jobId): void
    {
        error_log(
            'auth.startPasswordReset login=' . $this->pendingLogin
            . ' email=' . $this->pendingEmail
            . ' token=' . $this->pendingToken
            . ' job=' . $jobId,
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
