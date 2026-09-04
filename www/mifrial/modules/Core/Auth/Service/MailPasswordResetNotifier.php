<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Auth\Interface\Service\IPasswordResetNotifier;
use Mifrial\Core\Mail\Interface\Service\IMail;

/**
 * Кладёт reset в очередь Mail.
 */
final class MailPasswordResetNotifier implements IPasswordResetNotifier
{
    private const EVENT_CODE = 'auth.password_reset';

    /**
     * Создаёт notifier.
     *
     * @param IMail $mail Очередь.
     * @param AuthSettings $authSettings Срез auth.
     *
     * @return void
     */
    public function __construct(
        private readonly IMail $mail,
        private readonly AuthSettings $authSettings,
    ) {
    }

    /**
     * Ставит job события сброса.
     *
     * @param string $login Логин учётки.
     * @param string $rawToken Сырой токен.
     * @param string $email Почта.
     *
     * @return void
     */
    public function notify(string $login, string $rawToken, string $email): void
    {
        $this->mail->trigger(self::EVENT_CODE, [
            'login' => $login,
            'token' => $rawToken,
            'email' => $email,
        ]);
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
