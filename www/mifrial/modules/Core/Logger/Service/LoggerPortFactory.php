<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\Logger\Interface\Container\ILoggerContainer;
use Mifrial\Core\Logger\Repository\LogRepository;
use Mifrial\Core\Logger\Table\LogTable;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;

/**
 * Сборка TableLogWriter и HTTP из локатора.
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
        return new TableLogWriter($this->logRepository($serviceLocator));
    }

    /**
     * Создаёт HTTP-сценарий.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoggerHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function createHttp(IServiceLocator $serviceLocator): LoggerHttpService
    {
        return new LoggerHttpService(
            $this->userAccess($serviceLocator),
            $this->logRepository($serviceLocator),
            new LogFindPageParser(),
            new LogViewAssembler(),
        );
    }

    /**
     * HTTP из контейнера Logger.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LoggerHttpService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function fromContainer(IServiceLocator $serviceLocator): LoggerHttpService
    {
        $loggerContainer = $serviceLocator->get(ILoggerContainer::class);
        $loggerHttpService = $loggerContainer->get(LoggerHttpService::class);
        if (!$loggerHttpService instanceof LoggerHttpService) {
            throw new KernelException('PORT_TYPE', 'Logger HTTP service has a wrong type');
        }

        return $loggerHttpService;
    }

    /**
     * Репозиторий строк.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return LogRepository Строки.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function logRepository(IServiceLocator $serviceLocator): LogRepository
    {
        return new LogRepository($this->smartTableGateway($serviceLocator)->open(LogTable::class)->records());
    }

    /**
     * Guard учёток.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Guard.
     *
     * @throws KernelException Если тип чужой.
     */
    private function userAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $userAccess = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Logger HTTP requires IUserAccess');
        }

        return $userAccess;
    }

    /**
     * Шлюз ST.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $smartTableGateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Logger requires ISmartTableGateway');
        }

        return $smartTableGateway;
    }
}
