<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Service;

use Mifrial\Core\Cache\Interface\Container\ICacheContainer;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Versioning\Space\Interface\Service\IVersionedCatalog;

/**
 * Прод-каталог с пустым реестром фикстур.
 */
final class SpacePortFactory
{
    /**
     * Собирает каталог из локатора.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IVersionedCatalog Каталог.
     *
     * @throws KernelException Если порт соседа не того типа.
     */
    public function create(IServiceLocator $serviceLocator): IVersionedCatalog
    {
        $smartTableGateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Space requires ISmartTableGateway');
        }

        $cacheStore = $serviceLocator->get(ICacheContainer::class)->get(ICacheStore::class);
        if (!$cacheStore instanceof ICacheStore) {
            throw new KernelException('PORT_TYPE', 'Space requires ICacheStore');
        }

        $runtimeConfig = $serviceLocator->get(IKernelContainer::class)->get(IRuntimeConfig::class);
        if (!$runtimeConfig instanceof IRuntimeConfig) {
            throw new KernelException('PORT_TYPE', 'Space requires IRuntimeConfig');
        }

        return new VersionedCatalog($smartTableGateway, $cacheStore, $runtimeConfig->isDebug(), []);
    }
}
