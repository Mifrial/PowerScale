<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * JSON входа `auth.getPasswordPolicy`.
 */
final class GetPasswordPolicyInput implements IActionInput
{
    /**
     * Собирает вход; без userId — default.
     *
     * @param int|null $userId Целевая учётка.
     *
     * @return void
     */
    public function __construct(
        public readonly ?int $userId = null,
    ) {
    }
}
