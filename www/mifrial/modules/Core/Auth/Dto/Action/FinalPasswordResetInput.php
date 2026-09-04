<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * JSON входа `auth.finalPasswordReset`.
 */
final class FinalPasswordResetInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $login Логин.
     * @param string $resetToken Сырой токен.
     * @param string $newPassword Новый пароль.
     *
     * @return void
     */
    public function __construct(
        public readonly string $login,
        public readonly string $resetToken,
        public readonly string $newPassword,
    ) {
    }
}
