<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Repository\UserGroupMemberRepository;
use Mifrial\Core\User\Repository\UserGroupRepository;
use Mifrial\Core\User\Repository\UserRepository;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;

/**
 * Сборка фасада групп: репозитории внутри, не в карте портов.
 */
final class UserGroupsPortFactory
{
    /**
     * Создаёт фасад.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IUserGroups Фасад.
     *
     * @throws KernelException Если нет шлюза SmartTable.
     */
    public function create(IServiceLocator $serviceLocator): IUserGroups
    {
        $smartTableContainer = $serviceLocator->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'User requires ISmartTableGateway');
        }

        return new UserGroups(
            new UserGroupRepository($smartTableGateway->open(UserGroupTable::class)->records()),
            new UserGroupMemberRepository($smartTableGateway->open(UserGroupMemberTable::class)->records()),
            new UserRepository($smartTableGateway->open(UserTable::class)->records()),
        );
    }
}
