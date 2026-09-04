<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * JSON входа `auth.startPasswordReset`.
 */
final class StartPasswordResetInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $loginOrEmail Логин или почта.
     *
     * @return void
     */
    public function __construct(
        public readonly string $loginOrEmail,
    ) {
    }
}
