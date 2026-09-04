<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Repository\UserGroupMemberRepository;
use Mifrial\Core\User\Table\UserGroupMemberTable;

/**
 * Сборка HTTP-сценария групп.
 */
final class GroupHttpServiceFactory
{
    /**
     * Создаёт сценарий.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GroupHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function create(IServiceLocator $serviceLocator): GroupHttpService
    {
        $userContainer = $serviceLocator->get(IUserContainer::class);
        $userAccess = $userContainer->get(IUserAccess::class);
        $userGroups = $userContainer->get(IUserGroups::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        if (
            !$userAccess instanceof IUserAccess
            || !$userGroups instanceof IUserGroups
            || !$userAccounts instanceof IUserAccounts
        ) {
            throw new KernelException('PORT_TYPE', 'User group HTTP ports have a wrong type');
        }

        $smartTableContainer = $serviceLocator->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'User group HTTP requires ISmartTableGateway');
        }

        return new GroupHttpService(
            $userAccess,
            $userGroups,
            $userAccounts,
            new UserGroupMemberRepository($smartTableGateway->open(UserGroupMemberTable::class)->records()),
        );
    }

    /**
     * Берёт сценарий из контейнера User.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GroupHttpService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function fromContainer(IServiceLocator $serviceLocator): GroupHttpService
    {
        $userContainer = $serviceLocator->get(IUserContainer::class);
        $groupHttpService = $userContainer->get(GroupHttpService::class);
        if (!$groupHttpService instanceof GroupHttpService) {
            throw new KernelException('PORT_TYPE', 'User group HTTP service has a wrong type');
        }

        return $groupHttpService;
    }
}
