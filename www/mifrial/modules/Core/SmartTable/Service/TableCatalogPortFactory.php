<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Container\IModuleContainer;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ITableCatalog;

/**
 * Сборка каталога из уже созданного адаптера соединения.
 */
final class TableCatalogPortFactory
{
    /**
     * Собирает каталог на том же адаптере, что ping.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param IModuleContainer $moduleContainer Контейнер SmartTable.
     *
     * @return ITableCatalog Каталог.
     *
     * @throws DbConfigInvalidException Если порт соединения не Illuminate-адаптер.
     */
    public function create(
        IServiceLocator $serviceLocator,
        IModuleContainer $moduleContainer,
    ): ITableCatalog {
        $databaseConnection = $moduleContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('SmartTable connection adapter must be Illuminate');
        }

        $runtimeConfig = $serviceLocator->get(IKernelContainer::class)->get(IRuntimeConfig::class);
        if (!$runtimeConfig instanceof IRuntimeConfig) {
            throw new DbConfigInvalidException('Runtime cache config is missing');
        }

        return (new SmartTableSupport(
            $databaseConnection,
            $runtimeConfig->cache(),
            $runtimeConfig->debug(),
        ))->makeCatalog();
    }
}
