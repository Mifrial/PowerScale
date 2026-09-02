<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Exception;

/**
 * Пароль не проходит политику.
 */
final class AuthPolicyException extends AuthException
{
    /**
     * Создаёт AUTH_POLICY.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('AUTH_POLICY', 'Password does not meet the policy');
    }
}
