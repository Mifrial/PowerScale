<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Kernel\Interface\Http\IRequestBinder;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;

/**
 * Cookie сессии → актор запроса.
 */
final class AuthSessionBinder implements IRequestBinder
{
    /**
     * Создаёт binder.
     *
     * @param AuthService $authService Сессия.
     *
     * @return void
     */
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    /**
     * Кладёт актора или null.
     *
     * @param IRequestContext $requestContext Контекст.
     *
     * @return void
     */
    public function bind(IRequestContext $requestContext): void
    {
        $requestContext->setActor($this->authService->resolveActor());
    }
}
