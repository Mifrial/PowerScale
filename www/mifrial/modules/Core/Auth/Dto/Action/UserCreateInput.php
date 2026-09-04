<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Dto\Action;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Плоский JSON `user.create`.
 */
final class UserCreateInput implements IActionInput
{
    /**
     * Собирает вход create.
     *
     * @param string $name Имя.
     * @param string $login Логин.
     * @param string $password Пароль.
     * @param array<int, mixed> $groups Id групп.
     * @param string|null $email Почта; null/absent — без почты.
     * @param string|null $surname Фамилия; null — не писать.
     * @param string|null $nickname Псевдоним; null — не писать.
     *
     * @return void
     */
    public function __construct(
        public readonly string $name,
        public readonly string $login,
        public readonly string $password,
        public readonly array $groups = [],
        public readonly ?string $email = null,
        public readonly ?string $surname = null,
        public readonly ?string $nickname = null,
    ) {
    }
}
