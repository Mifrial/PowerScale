<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Service;

use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

/**
 * Порт ICacheStore из RuntimeConfig.
 */
final class CachePortFactory
{
    /**
     * Собирает один store на процесс.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICacheStore Store; дырявый конфиг — непригодный объект.
     *
     * @throws KernelException Если нет IRuntimeConfig.
     */
    public function create(IServiceLocator $serviceLocator): ICacheStore
    {
        $runtimeConfig = $serviceLocator->get(IKernelContainer::class)->get(IRuntimeConfig::class);
        if (!$runtimeConfig instanceof IRuntimeConfig) {
            throw new KernelException('PORT_TYPE', 'Cache requires IRuntimeConfig');
        }

        return (new CacheStoreFactory())->open($runtimeConfig->cache());
    }
}
