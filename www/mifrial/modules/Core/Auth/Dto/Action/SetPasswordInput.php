<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * JSON входа `auth.setPassword`.
 */
final class SetPasswordInput implements IActionInput
{
    /**
     * Собирает вход смены пароля.
     *
     * @param int $userId Целевая учётка.
     * @param string $newPassword Новый пароль.
     * @param string|null $currentPassword Текущий; себе обязателен.
     *
     * @return void
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $newPassword,
        public readonly ?string $currentPassword = null,
    ) {
    }
}
