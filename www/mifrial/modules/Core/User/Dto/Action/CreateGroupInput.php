<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;

/**
 * Плоский JSON `userGroup.create`.
 */
final class CreateGroupInput implements IActionInput
{
    /**
     * Собирает вход create.
     *
     * @param string $name Имя.
     * @param array<int|string, mixed> $permissions Ключи прав.
     * @param OptionalBool $active Активность.
     * @param OptionalBool $assignOnRegister Автовыдача.
     *
     * @return void
     */
    public function __construct(
        public readonly string $name,
        public readonly array $permissions,
        public readonly OptionalBool $active,
        public readonly OptionalBool $assignOnRegister,
    ) {
    }
}
