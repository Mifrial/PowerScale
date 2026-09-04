<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Interface\Service\IUserViews;
use Mifrial\Core\User\Repository\UserGroupMemberRepository;
use Mifrial\Core\User\Repository\UserGroupRepository;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;

/**
 * Сборка HTTP-сценария учётки.
 */
final class UserHttpServiceFactory
{
    /**
     * Создаёт сценарий.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return UserHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function create(IServiceLocator $serviceLocator): UserHttpService
    {
        $userContainer = $serviceLocator->get(IUserContainer::class);
        $userAccess = $userContainer->get(IUserAccess::class);
        $userViews = $userContainer->get(IUserViews::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        $userGroups = $userContainer->get(IUserGroups::class);
        if (
            !$userAccess instanceof IUserAccess
            || !$userViews instanceof IUserViews
            || !$userAccounts instanceof IUserAccounts
            || !$userGroups instanceof IUserGroups
        ) {
            throw new KernelException('PORT_TYPE', 'User HTTP ports have a wrong type');
        }

        return new UserHttpService(
            $userAccess,
            $userViews,
            $userAccounts,
            new UserMembershipSync($userAccess, $userGroups),
        );
    }

    /**
     * Создаёт guard.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Guard.
     *
     * @throws KernelException Если нет контекста.
     */
    public function createAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $kernelContainer = $serviceLocator->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        if (!$requestContext instanceof IRequestContext) {
            throw new KernelException('PORT_TYPE', 'User requires IRequestContext');
        }

        return new UserAccess($requestContext);
    }

    /**
     * Создаёт сборщик JSON.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserViews Сборщик.
     *
     * @throws KernelException Если нет шлюза SmartTable.
     */
    public function createViews(IServiceLocator $serviceLocator): IUserViews
    {
        $smartTableContainer = $serviceLocator->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'User views require ISmartTableGateway');
        }

        return new UserViewAssembler(
            new UserGroupRepository($smartTableGateway->open(UserGroupTable::class)->records()),
            new UserGroupMemberRepository($smartTableGateway->open(UserGroupMemberTable::class)->records()),
        );
    }

    /**
     * Берёт HTTP-сценарий из контейнера User.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return UserHttpService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function fromContainer(IServiceLocator $serviceLocator): UserHttpService
    {
        $userContainer = $serviceLocator->get(IUserContainer::class);
        $userHttpService = $userContainer->get(UserHttpService::class);
        if (!$userHttpService instanceof UserHttpService) {
            throw new KernelException('PORT_TYPE', 'User HTTP service has a wrong type');
        }

        return $userHttpService;
    }
}
