<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Repository\UserRepository;
use Mifrial\Core\User\Table\UserTable;

/**
 * Сборка фасада учётки: репозиторий внутри, не в карте портов.
 */
final class UserAccountsPortFactory
{
    /**
     * Создаёт фасад.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IUserAccounts Фасад.
     *
     * @throws KernelException Если нет шлюза SmartTable.
     */
    public function create(IServiceLocator $serviceLocator): IUserAccounts
    {
        $smartTableContainer = $serviceLocator->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'User requires ISmartTableGateway');
        }

        return new UserAccounts(new UserRepository(
            $smartTableGateway->open(UserTable::class)->records(),
        ));
    }
}
