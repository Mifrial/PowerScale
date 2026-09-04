<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Interface\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * Guards HTTP учётки по снимку актора.
 */
interface IUserAccess
{
    /**
     * Требует актора сессии.
     *
     * @return RequestActor Актор.
     *
     * @throws ActionException AUTH_REQUIRED, если актора нет.
     */
    public function requireActor(): RequestActor;

    /**
     * Требует ключ или bypass.
     *
     * @param string $permissionKey Ключ вроде user.view.
     *
     * @return RequestActor Актор.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function requireKey(string $permissionKey): RequestActor;

    /**
     * Свой id или ключ.
     *
     * @param int $userId Целевая учётка.
     * @param string $permissionKey Ключ без owner-исключения.
     *
     * @return RequestActor Актор.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function requireSelfOrKey(int $userId, string $permissionKey): RequestActor;

    /**
     * Менять членство bypass-группы может только актор с hasBypass.
     *
     * @param bool $groupHasBypass Группа с bypass=true.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function assertCanAssignBypassMembership(bool $groupHasBypass): void;

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
    public function assertCanUpdate(int $userId, bool $touchesGroups, bool $touchesActive): void;

    /**
     * deactivate: ключ user.deactivate, не себя.
     *
     * @param int $userId Целевая учётка.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function assertCanDeactivate(int $userId): void;
}
