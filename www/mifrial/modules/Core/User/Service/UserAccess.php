<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\User\Interface\Service\IUserAccess;

/**
 * Guards по IRequestContext, без Auth.
 */
final class UserAccess implements IUserAccess
{
    /**
     * Создаёт guard.
     *
     * @param IRequestContext $requestContext Контекст процесса.
     *
     * @return void
     */
    public function __construct(
        private readonly IRequestContext $requestContext,
    ) {
    }

    /**
     * Требует актора сессии.
     *
     * @return RequestActor Актор.
     *
     * @throws ActionException AUTH_REQUIRED, если актора нет.
     */
    public function requireActor(): RequestActor
    {
        $requestActor = $this->requestContext->getActor();
        if ($requestActor instanceof RequestActor) {
            return $requestActor;
        }

        throw new ActionException('AUTH_REQUIRED', 'Authentication is required');
    }

    /**
     * Требует ключ или bypass.
     *
     * @param string $permissionKey Ключ.
     *
     * @return RequestActor Актор.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function requireKey(string $permissionKey): RequestActor
    {
        $requestActor = $this->requireActor();
        if ($requestActor->hasKey($permissionKey)) {
            return $requestActor;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * Свой id или ключ.
     *
     * @param int $userId Целевая учётка.
     * @param string $permissionKey Ключ.
     *
     * @return RequestActor Актор.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function requireSelfOrKey(int $userId, string $permissionKey): RequestActor
    {
        $requestActor = $this->requireActor();
        if ($requestActor->getUserId() === $userId || $requestActor->hasKey($permissionKey)) {
            return $requestActor;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * Менять членство bypass-группы может только актор с hasBypass.
     *
     * @param bool $groupHasBypass Группа с bypass=true.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function assertCanAssignBypassMembership(bool $groupHasBypass): void
    {
        if (!$groupHasBypass) {
            return;
        }

        if ($this->requireActor()->hasBypass()) {
            return;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * update: user.edit или свой id без ключей groups/active.
     *
     * @param int $userId Целевая учётка.
     * @param bool $touchesGroups Ключ groups был.
     * @param bool $touchesActive Ключ active был.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function assertCanUpdate(int $userId, bool $touchesGroups, bool $touchesActive): void
    {
        $requestActor = $this->requireActor();
        if ($requestActor->hasKey('user.edit')) {
            return;
        }

        if ($requestActor->getUserId() === $userId && !$touchesGroups && !$touchesActive) {
            return;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * deactivate: ключ user.deactivate, не себя.
     *
     * @param int $userId Целевая учётка.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function assertCanDeactivate(int $userId): void
    {
        $requestActor = $this->requireKey('user.deactivate');
        if ($requestActor->getUserId() === $userId) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }
    }
}
