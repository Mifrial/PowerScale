<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Core\File\Interface\Container\IFileContainer;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\Kernel\Interface\Service\ITransactionRunner;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\SmartTableTransactionRunner;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Library\Asset\Interface\Container\IAssetContainer;
use Mifrial\Library\Asset\Interface\Service\IAssetCollections;
use Mifrial\Roleplay\Scene\Interface\Container\ISceneContainer;
use Mifrial\Roleplay\Scene\Repository\SceneBoardRepository;
use Mifrial\Roleplay\Scene\Repository\SceneElementRepository;
use Mifrial\Roleplay\Scene\Repository\SceneEnclosureRepository;
use Mifrial\Roleplay\Scene\Repository\SceneLocationRepository;
use Mifrial\Roleplay\Scene\Setup\SceneModuleSetup;
use Mifrial\Roleplay\Scene\Table\SceneBoardTable;
use Mifrial\Roleplay\Scene\Table\SceneElementTable;
use Mifrial\Roleplay\Scene\Table\SceneEnclosureTable;
use Mifrial\Roleplay\Scene\Table\SceneLocationTable;

/**
 * Сборка сервисов Roleplay/Scene из локатора.
 */
final class ScenePortFactory
{
    /**
     * Setup модуля с коллекциями ассетов.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneModuleSetup Setup.
     */
    public function createSetup(IServiceLocator $serviceLocator): SceneModuleSetup
    {
        return new SceneModuleSetup($this->assetCollections($serviceLocator));
    }

    /**
     * HTTP каталога.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneCatalogHttp Сценарий.
     */
    public function createCatalogHttp(IServiceLocator $serviceLocator): SceneCatalogHttp
    {
        return new SceneCatalogHttp(
            $this->accessService($serviceLocator),
            $this->catalogService($serviceLocator),
            $this->detailService($serviceLocator),
            $this->viewAssembler($serviceLocator),
        );
    }

    /**
     * HTTP сохранения.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneSaveHttp Сценарий.
     */
    public function createSaveHttp(IServiceLocator $serviceLocator): SceneSaveHttp
    {
        return new SceneSaveHttp(
            $this->accessService($serviceLocator),
            $this->saveService($serviceLocator),
            $this->viewAssembler($serviceLocator),
        );
    }

    /**
     * HTTP каталога из контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneCatalogHttp Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function fromCatalogContainer(IServiceLocator $serviceLocator): SceneCatalogHttp
    {
        $service = $serviceLocator->get(ISceneContainer::class)->get(SceneCatalogHttp::class);
        if (!$service instanceof SceneCatalogHttp) {
            throw new KernelException('PORT_TYPE', 'Scene catalog HTTP has a wrong type');
        }

        return $service;
    }

    /**
     * HTTP сохранения из контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneSaveHttp Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function fromSaveContainer(IServiceLocator $serviceLocator): SceneSaveHttp
    {
        $service = $serviceLocator->get(ISceneContainer::class)->get(SceneSaveHttp::class);
        if (!$service instanceof SceneSaveHttp) {
            throw new KernelException('PORT_TYPE', 'Scene save HTTP has a wrong type');
        }

        return $service;
    }

    /**
     * Доступ.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneAccessService Сервис.
     */
    private function accessService(IServiceLocator $serviceLocator): SceneAccessService
    {
        return new SceneAccessService(
            $this->userAccess($serviceLocator),
            $this->locationRepository($serviceLocator),
        );
    }

    /**
     * Каталог.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneCatalogService Сервис.
     */
    private function catalogService(IServiceLocator $serviceLocator): SceneCatalogService
    {
        return new SceneCatalogService(
            $this->locationRepository($serviceLocator),
            $this->accessService($serviceLocator),
        );
    }

    /**
     * Снимок.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneDetailService Сервис.
     */
    private function detailService(IServiceLocator $serviceLocator): SceneDetailService
    {
        return new SceneDetailService(
            $this->accessService($serviceLocator),
            $this->boardRepository($serviceLocator),
            $this->elementRepository($serviceLocator),
            $this->enclosureRepository($serviceLocator),
        );
    }

    /**
     * Сохранение.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneSaveService Сервис.
     */
    private function saveService(IServiceLocator $serviceLocator): SceneSaveService
    {
        return new SceneSaveService(
            $this->accessService($serviceLocator),
            $this->saveValidator($serviceLocator),
            $this->saveWriter($serviceLocator),
            $this->transactionRunner($serviceLocator),
            $this->detailService($serviceLocator),
        );
    }

    /**
     * Валидатор снимка.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneSaveValidator Валидатор.
     */
    private function saveValidator(IServiceLocator $serviceLocator): SceneSaveValidator
    {
        $fields = new SceneSaveFields();
        $files = new SceneSaveFileResolver($this->files($serviceLocator));
        $bounds = new SceneBoardBounds();

        return new SceneSaveValidator(
            $fields,
            $files,
            new SceneElementValidator($fields, $files, $bounds),
            $bounds,
        );
    }

    /**
     * Писатель снимка.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneSaveWriter Писатель.
     */
    private function saveWriter(IServiceLocator $serviceLocator): SceneSaveWriter
    {
        return new SceneSaveWriter(
            $this->locationRepository($serviceLocator),
            $this->boardRepository($serviceLocator),
            $this->elementRepository($serviceLocator),
            $this->enclosureRepository($serviceLocator),
            new SceneSaveRows(),
        );
    }

    /**
     * Представление JSON.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneViewAssembler Сборщик.
     */
    private function viewAssembler(IServiceLocator $serviceLocator): SceneViewAssembler
    {
        return new SceneViewAssembler($this->files($serviceLocator));
    }

    /**
     * Локации.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneLocationRepository Строки.
     */
    private function locationRepository(IServiceLocator $serviceLocator): SceneLocationRepository
    {
        return new SceneLocationRepository(
            $this->smartTableGateway($serviceLocator)->open(SceneLocationTable::class)->records(),
            new SceneRowErrors(),
        );
    }

    /**
     * Поля.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneBoardRepository Строки.
     */
    private function boardRepository(IServiceLocator $serviceLocator): SceneBoardRepository
    {
        return new SceneBoardRepository(
            $this->smartTableGateway($serviceLocator)->open(SceneBoardTable::class)->records(),
            new SceneRowErrors(),
        );
    }

    /**
     * Элементы.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneElementRepository Строки.
     */
    private function elementRepository(IServiceLocator $serviceLocator): SceneElementRepository
    {
        return new SceneElementRepository(
            $this->smartTableGateway($serviceLocator)->open(SceneElementTable::class)->records(),
            new SceneRowErrors(),
        );
    }

    /**
     * Вложения.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return SceneEnclosureRepository Строки.
     */
    private function enclosureRepository(IServiceLocator $serviceLocator): SceneEnclosureRepository
    {
        return new SceneEnclosureRepository(
            $this->smartTableGateway($serviceLocator)->open(SceneEnclosureTable::class)->records(),
            new SceneRowErrors(),
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
            throw new KernelException('PORT_TYPE', 'Scene requires IUserAccess');
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
            throw new KernelException('PORT_TYPE', 'Scene requires IFiles');
        }

        return $files;
    }

    /**
     * Коллекции ассетов.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IAssetCollections Порт.
     *
     * @throws KernelException Если порт неверного типа.
     */
    private function assetCollections(IServiceLocator $serviceLocator): IAssetCollections
    {
        $collections = $serviceLocator->get(IAssetContainer::class)->get(IAssetCollections::class);
        if (!$collections instanceof IAssetCollections) {
            throw new KernelException('PORT_TYPE', 'Scene requires IAssetCollections');
        }

        return $collections;
    }

    /**
     * Единица работы.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ITransactionRunner Runner.
     */
    private function transactionRunner(IServiceLocator $serviceLocator): ITransactionRunner
    {
        return new SmartTableTransactionRunner($this->smartTableGateway($serviceLocator));
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
            throw new KernelException('PORT_TYPE', 'Scene requires ISmartTableGateway');
        }

        return $gateway;
    }
}
