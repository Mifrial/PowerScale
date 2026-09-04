<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto\Action;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalArray;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Плоский JSON `user.update`.
 */
final class UpdateUserInput implements IActionInput
{
    /**
     * Собирает вход update.
     *
     * @param int $id Id учётки.
     * @param OptionalString $name Имя.
     * @param OptionalString $surname Фамилия.
     * @param OptionalString $nickname Псевдоним.
     * @param OptionalString $email Почта.
     * @param OptionalArray $groups Id групп.
     * @param OptionalBool $active Активность.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly OptionalString $name,
        public readonly OptionalString $surname,
        public readonly OptionalString $nickname,
        public readonly OptionalString $email,
        public readonly OptionalArray $groups,
        public readonly OptionalBool $active,
    ) {
    }
}
