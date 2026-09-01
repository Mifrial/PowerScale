<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;

/**
 * Фабрика порта соединения: читает локатор только здесь.
 */
final class DatabaseConnectionPortFactory
{
    /**
     * Собирает ленивый адаптер из RuntimeConfig Kernel.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IDatabaseConnection Адаптер соединения.
     *
     * @throws DbConfigInvalidException Если RuntimeConfig недоступен.
     */
    public function create(IServiceLocator $serviceLocator): IDatabaseConnection
    {
        $runtimeConfig = $serviceLocator->get(IKernelContainer::class)->get(IRuntimeConfig::class);
        if (!$runtimeConfig instanceof IRuntimeConfig) {
            throw new DbConfigInvalidException('Runtime database config is missing');
        }

        return new IlluminateDatabaseConnection(
            new IlluminateConnectionFactory(),
            $runtimeConfig->database(),
        );
    }
}
