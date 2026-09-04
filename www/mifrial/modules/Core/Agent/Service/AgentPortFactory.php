<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Service;

use Mifrial\Core\Agent\Interface\Service\IAgents;
use Mifrial\Core\Agent\Repository\AgentRepository;
use Mifrial\Core\Agent\Table\AgentTable;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;

/**
 * Сборка фасада Agent из локатора.
 */
final class AgentPortFactory
{
    /**
     * Создаёт фасад.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IAgents Фасад.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function create(IServiceLocator $serviceLocator): IAgents
    {
        $smartTableContainer = $serviceLocator->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Agent requires ISmartTableGateway');
        }

        return new AgentService(new AgentRepository(
            $smartTableGateway->open(AgentTable::class)->records(),
        ));
    }
}
