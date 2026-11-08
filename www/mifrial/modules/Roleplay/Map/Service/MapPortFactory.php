<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

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
use Mifrial\Roleplay\Map\Interface\Container\IMapContainer;
use Mifrial\Roleplay\Map\Repository\MapAreaGroupRepository;
use Mifrial\Roleplay\Map\Repository\MapAreaRepository;
use Mifrial\Roleplay\Map\Repository\MapAreaShapeRepository;
use Mifrial\Roleplay\Map\Repository\MapLineRepository;
use Mifrial\Roleplay\Map\Repository\MapNodeRepository;
use Mifrial\Roleplay\Map\Repository\MapPlacementRepository;
use Mifrial\Roleplay\Map\Repository\MapRepository;
use Mifrial\Roleplay\Map\Repository\MapVariantRepository;
use Mifrial\Roleplay\Map\Table\MapAreaGroupTable;
use Mifrial\Roleplay\Map\Table\MapAreaTable;
use Mifrial\Roleplay\Map\Table\MapNodeTable;
use Mifrial\Roleplay\Map\Table\MapTable;
use Mifrial\Roleplay\Map\Table\MapVariantAreaTable;
use Mifrial\Roleplay\Map\Table\MapVariantLineTable;
use Mifrial\Roleplay\Map\Table\MapVariantNodeTable;
use Mifrial\Roleplay\Map\Table\MapVariantTable;

/**
 * Сборка сервисов Roleplay/Map из локатора.
 */
final class MapPortFactory
{
    /**
     * HTTP каталога.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapCatalogHttp Сценарий.
     */
    public function createCatalogHttp(IServiceLocator $serviceLocator): MapCatalogHttp
    {
        return new MapCatalogHttp(
            $this->accessService($serviceLocator),
            $this->catalogService($serviceLocator),
            $this->detailService($serviceLocator),
            $this->viewAssembler($serviceLocator),
        );
    }

    /**
     * HTTP публикации.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapSaveHttp Сценарий.
     */
    public function createSaveHttp(IServiceLocator $serviceLocator): MapSaveHttp
    {
        return new MapSaveHttp(
            $this->accessService($serviceLocator),
            $this->saveService($serviceLocator),
            $this->viewAssembler($serviceLocator),
        );
    }

    /**
     * HTTP маршрута.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapRouteHttp Сценарий.
     */
    public function createRouteHttp(IServiceLocator $serviceLocator): MapRouteHttp
    {
        return new MapRouteHttp(
            $this->accessService($serviceLocator),
            $this->routeService($serviceLocator),
            $this->viewAssembler($serviceLocator),
        );
    }

    /**
     * HTTP каталога из контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapCatalogHttp Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function fromCatalogContainer(IServiceLocator $serviceLocator): MapCatalogHttp
    {
        $service = $serviceLocator->get(IMapContainer::class)->get(MapCatalogHttp::class);
        if (!$service instanceof MapCatalogHttp) {
            throw new KernelException('PORT_TYPE', 'Map catalog HTTP has a wrong type');
        }

        return $service;
    }

    /**
     * HTTP публикации из контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapSaveHttp Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function fromSaveContainer(IServiceLocator $serviceLocator): MapSaveHttp
    {
        $service = $serviceLocator->get(IMapContainer::class)->get(MapSaveHttp::class);
        if (!$service instanceof MapSaveHttp) {
            throw new KernelException('PORT_TYPE', 'Map save HTTP has a wrong type');
        }

        return $service;
    }

    /**
     * HTTP маршрута из контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapRouteHttp Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function fromRouteContainer(IServiceLocator $serviceLocator): MapRouteHttp
    {
        $service = $serviceLocator->get(IMapContainer::class)->get(MapRouteHttp::class);
        if (!$service instanceof MapRouteHttp) {
            throw new KernelException('PORT_TYPE', 'Map route HTTP has a wrong type');
        }

        return $service;
    }

    /**
     * Доступ.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapAccessService Сервис.
     */
    private function accessService(IServiceLocator $serviceLocator): MapAccessService
    {
        return new MapAccessService(
            $this->userAccess($serviceLocator),
            $this->mapRepository($serviceLocator),
        );
    }

    /**
     * Варианты.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapVariantService Сервис.
     */
    private function variantService(IServiceLocator $serviceLocator): MapVariantService
    {
        return new MapVariantService($this->variantRepository($serviceLocator));
    }

    /**
     * Каталог.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapCatalogService Сервис.
     */
    private function catalogService(IServiceLocator $serviceLocator): MapCatalogService
    {
        return new MapCatalogService(
            $this->mapRepository($serviceLocator),
            $this->accessService($serviceLocator),
        );
    }

    /**
     * Публикация.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapSaveService Сервис.
     */
    private function saveService(IServiceLocator $serviceLocator): MapSaveService
    {
        return new MapSaveService(
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
     * @return MapSaveValidator Валидатор.
     */
    private function saveValidator(IServiceLocator $serviceLocator): MapSaveValidator
    {
        return new MapSaveValidator(
            new MapSaveFields(),
            new MapSaveSets(),
            $this->files($serviceLocator),
            new MapGraphSaveValidator(
                new MapSaveFields(),
                new MapSaveSets(),
            ),
        );
    }

    /**
     * Писатель снимка.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapSaveWriter Писатель.
     */
    private function saveWriter(IServiceLocator $serviceLocator): MapSaveWriter
    {
        return new MapSaveWriter(
            $this->mapRepository($serviceLocator),
            new MapVariantWriter($this->variantRepository($serviceLocator)),
            new MapNodeWriter($this->nodeStore($serviceLocator)),
            new MapGroupSaveWriter($this->groupStore($serviceLocator)),
            new MapGraphSaveWriter($this->areaStore($serviceLocator)),
            new MapLineSaveWriter($this->lineStore($serviceLocator)),
            new MapLinkSaveWriter(
                $this->nodeStore($serviceLocator),
                $this->areaStore($serviceLocator),
            ),
        );
    }

    /**
     * Узлы.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapNodeStore Стор.
     */
    private function nodeStore(IServiceLocator $serviceLocator): MapNodeStore
    {
        $gateway = $this->smartTableGateway($serviceLocator);

        return new MapNodeStore(
            new MapNodeRepository($gateway->open(MapNodeTable::class)->records()),
            new MapPlacementRepository($gateway->open(MapVariantNodeTable::class)->records()),
        );
    }

    /**
     * Области.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapAreaStore Стор.
     */
    private function areaStore(IServiceLocator $serviceLocator): MapAreaStore
    {
        $gateway = $this->smartTableGateway($serviceLocator);

        return new MapAreaStore(
            new MapAreaRepository($gateway->open(MapAreaTable::class)->records()),
            new MapAreaShapeRepository($gateway->open(MapVariantAreaTable::class)->records()),
        );
    }

    /**
     * Группы областей.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapAreaGroupStore Стор.
     */
    private function groupStore(IServiceLocator $serviceLocator): MapAreaGroupStore
    {
        return new MapAreaGroupStore(
            new MapAreaGroupRepository(
                $this->smartTableGateway($serviceLocator)->open(MapAreaGroupTable::class)->records(),
            ),
        );
    }

    /**
     * Ленты.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapLineStore Стор.
     */
    private function lineStore(IServiceLocator $serviceLocator): MapLineStore
    {
        return new MapLineStore(
            new MapLineRepository(
                $this->smartTableGateway($serviceLocator)->open(MapVariantLineTable::class)->records(),
            ),
        );
    }

    /**
     * Снимок.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapDetailService Сервис.
     */
    private function detailService(IServiceLocator $serviceLocator): MapDetailService
    {
        return new MapDetailService(
            $this->accessService($serviceLocator),
            $this->variantService($serviceLocator),
            $this->nodeStore($serviceLocator),
            $this->areaStore($serviceLocator),
            $this->groupStore($serviceLocator),
            $this->lineStore($serviceLocator),
        );
    }

    /**
     * Маршрут.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapRouteService Сервис.
     */
    private function routeService(IServiceLocator $serviceLocator): MapRouteService
    {
        $geometry = new MapGeometry();

        return new MapRouteService(
            $this->accessService($serviceLocator),
            $this->variantService($serviceLocator),
            $this->areaStore($serviceLocator),
            $this->lineStore($serviceLocator),
            new MapRouteGrid($geometry),
            $geometry,
        );
    }

    /**
     * Представление JSON.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapViewAssembler Сборщик.
     */
    private function viewAssembler(IServiceLocator $serviceLocator): MapViewAssembler
    {
        return new MapViewAssembler($this->files($serviceLocator));
    }

    /**
     * Карты.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapRepository Строки.
     */
    private function mapRepository(IServiceLocator $serviceLocator): MapRepository
    {
        return new MapRepository(
            $this->smartTableGateway($serviceLocator)->open(MapTable::class)->records(),
        );
    }

    /**
     * Варианты.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MapVariantRepository Строки.
     */
    private function variantRepository(IServiceLocator $serviceLocator): MapVariantRepository
    {
        return new MapVariantRepository(
            $this->smartTableGateway($serviceLocator)->open(MapVariantTable::class)->records(),
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
            throw new KernelException('PORT_TYPE', 'Map requires IUserAccess');
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
            throw new KernelException('PORT_TYPE', 'Map requires IFiles');
        }

        return $files;
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
            throw new KernelException('PORT_TYPE', 'Map requires ISmartTableGateway');
        }

        return $gateway;
    }
}
