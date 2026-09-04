<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalArray;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Плоский JSON `userGroup.update`.
 */
final class UpdateGroupInput implements IActionInput
{
    /**
     * Собирает вход update.
     *
     * @param int $id Id группы.
     * @param OptionalString $name Имя.
     * @param OptionalArray $permissions Ключи прав.
     * @param OptionalBool $active Активность.
     * @param OptionalBool $assignOnRegister Автовыдача.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly OptionalString $name,
        public readonly OptionalArray $permissions,
        public readonly OptionalBool $active,
        public readonly OptionalBool $assignOnRegister,
    ) {
    }
}
