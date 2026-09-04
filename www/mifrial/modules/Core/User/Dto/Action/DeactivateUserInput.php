<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Плоский JSON `user.deactivate`.
 */
final class DeactivateUserInput implements IActionInput
{
    /**
     * Собирает вход deactivate.
     *
     * @param int $id Id учётки.
     * @param OptionalString $reason Причина.
     * @param OptionalString $deactivatedUntil Дата Y-m-d.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly OptionalString $reason,
        public readonly OptionalString $deactivatedUntil,
    ) {
    }
}
