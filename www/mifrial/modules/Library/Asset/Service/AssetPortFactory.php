<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Service;

use Mifrial\Core\File\Interface\Container\IFileContainer;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Library\Asset\Interface\Container\IAssetContainer;
use Mifrial\Library\Asset\Repository\AssetCollectionRepository;
use Mifrial\Library\Asset\Repository\AssetItemRepository;
use Mifrial\Library\Asset\Table\AssetCollectionTable;
use Mifrial\Library\Asset\Table\AssetItemTable;

/**
 * Сборка сервисов Library/Asset из локатора.
 */
final class AssetPortFactory
{
    /**
     * Порт коллекций для setup модулей-потребителей.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return AssetCollectionService Сервис.
     */
    public function createCollections(IServiceLocator $serviceLocator): AssetCollectionService
    {
        return new AssetCollectionService(
            $this->collectionRepository($serviceLocator),
            new AssetInputValidator(),
        );
    }

    /**
     * HTTP сценарий библиотеки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return AssetHttpService Сценарий.
     */
    public function createHttp(IServiceLocator $serviceLocator): AssetHttpService
    {
        return new AssetHttpService(
            $this->userAccess($serviceLocator),
            $this->collectionRepository($serviceLocator),
            $this->itemRepository($serviceLocator),
            new AssetFileResolver($this->files($serviceLocator)),
            new AssetViewAssembler(),
            new AssetInputValidator(),
        );
    }

    /**
     * HTTP сценарий из контейнера модуля.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return AssetHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function fromContainer(IServiceLocator $serviceLocator): AssetHttpService
    {
        $service = $serviceLocator->get(IAssetContainer::class)->get(AssetHttpService::class);
        if (!$service instanceof AssetHttpService) {
            throw new KernelException('PORT_TYPE', 'Asset HTTP has a wrong type');
        }

        return $service;
    }

    /**
     * Строки коллекций.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return AssetCollectionRepository Репозиторий.
     */
    private function collectionRepository(IServiceLocator $serviceLocator): AssetCollectionRepository
    {
        return new AssetCollectionRepository(
            $this->smartTableGateway($serviceLocator)->open(AssetCollectionTable::class)->records(),
            new AssetRowErrors(),
        );
    }

    /**
     * Строки ассетов.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return AssetItemRepository Репозиторий.
     */
    private function itemRepository(IServiceLocator $serviceLocator): AssetItemRepository
    {
        return new AssetItemRepository(
            $this->smartTableGateway($serviceLocator)->open(AssetItemTable::class)->records(),
            new AssetRowErrors(),
        );
    }

    /**
     * Guard учёток.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Guard.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function userAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $userAccess = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Asset requires IUserAccess');
        }

        return $userAccess;
    }

    /**
     * Файлы.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IFiles Порт.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function files(IServiceLocator $serviceLocator): IFiles
    {
        $files = $serviceLocator->get(IFileContainer::class)->get(IFiles::class);
        if (!$files instanceof IFiles) {
            throw new KernelException('PORT_TYPE', 'Asset requires IFiles');
        }

        return $files;
    }

    /**
     * Шлюз ST.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $gateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$gateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Asset requires ISmartTableGateway');
        }

        return $gateway;
    }
}
