<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\Logger\Repository\LogRepository;
use Mifrial\Core\Logger\Table\LogTable;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;

/**
 * Сборка TableLogWriter из локатора.
 */
final class LoggerPortFactory
{
    /**
     * Создаёт табличный адаптер.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return TableLogWriter Писатель.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function create(IServiceLocator $serviceLocator): TableLogWriter
    {
        $smartTableContainer = $serviceLocator->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Logger requires ISmartTableGateway');
        }

        return new TableLogWriter(new LogRepository(
            $smartTableGateway->open(LogTable::class)->records(),
        ));
    }
}
