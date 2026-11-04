<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests\Fixture;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\User\Interface\Service\IUserAccess;

/**
 * Guard с заданным актором для unit-тестов File.
 */
final class FakeUserAccess implements IUserAccess
{
    /**
     * Создаёт guard.
     *
     * @param RequestActor|null $requestActor Актор или null.
     *
     * @return void
     */
    public function __construct(
        private readonly ?RequestActor $requestActor = null,
    ) {
    }

    /**
     * Возвращает актора или AUTH_REQUIRED.
     *
     * @return RequestActor Актор.
     */
    public function requireActor(): RequestActor
    {
        if (!$this->requestActor instanceof RequestActor) {
            throw new ActionException('AUTH_REQUIRED', 'Actor is required');
        }

        return $this->requestActor;
    }

    /**
     * Требует ключ или bypass.
     *
     * @param string $permissionKey Ключ.
     *
     * @return RequestActor Актор.
     */
    public function requireKey(string $permissionKey): RequestActor
    {
        $requestActor = $this->requireActor();
        if (!$requestActor->hasKey($permissionKey)) {
            throw new ActionException('AUTH_DENIED', 'Permission is denied');
        }

        return $requestActor;
    }

    /**
     * Не используется в тестах File.
     *
     * @param int $userId Учётка.
     * @param string $permissionKey Ключ.
     *
     * @return RequestActor Актор.
     */
    public function requireSelfOrKey(int $userId, string $permissionKey): RequestActor
    {
        throw new KernelException('AUTH_DENIED', 'Not supported by the fake');
    }

    /**
     * Не используется в тестах File.
     *
     * @param bool $groupHasBypass Группа с bypass.
     *
     * @return void
     */
    public function assertCanAssignBypassMembership(bool $groupHasBypass): void
    {
        throw new KernelException('AUTH_DENIED', 'Not supported by the fake');
    }

    /**
     * Не используется в тестах File.
     *
     * @param int $userId Учётка.
     * @param bool $touchesGroups Ключ groups.
     * @param bool $touchesActive Ключ active.
     *
     * @return void
     */
    public function assertCanUpdate(int $userId, bool $touchesGroups, bool $touchesActive): void
    {
        throw new KernelException('AUTH_DENIED', 'Not supported by the fake');
    }

    /**
     * Не используется в тестах File.
     *
     * @param int $userId Учётка.
     *
     * @return void
     */
    public function assertCanDeactivate(int $userId): void
    {
        throw new KernelException('AUTH_DENIED', 'Not supported by the fake');
    }
}
