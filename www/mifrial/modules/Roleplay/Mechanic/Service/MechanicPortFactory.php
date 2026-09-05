<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Mechanic\Repository\MechanicRepository;
use Mifrial\Roleplay\Mechanic\Table\MechanicTable;

/**
 * Сборка фасада и HTTP механик из локатора.
 */
final class MechanicPortFactory
{
    /**
     * Создаёт фасад.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IMechanics Фасад.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function create(IServiceLocator $serviceLocator): IMechanics
    {
        return new Mechanics(
            new MechanicRepository($this->smartTableGateway($serviceLocator)->open(MechanicTable::class)->records()),
        );
    }

    /**
     * Создаёт HTTP-сценарий.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MechanicHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function createHttp(IServiceLocator $serviceLocator): MechanicHttpService
    {
        return new MechanicHttpService(
            $this->userAccess($serviceLocator),
            $this->mechanics($serviceLocator),
            new MechanicViewAssembler(),
        );
    }

    /**
     * HTTP из контейнера Mechanic.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MechanicHttpService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function fromContainer(IServiceLocator $serviceLocator): MechanicHttpService
    {
        $mechanicContainer = $serviceLocator->get(IMechanicContainer::class);
        $mechanicHttpService = $mechanicContainer->get(MechanicHttpService::class);
        if (!$mechanicHttpService instanceof MechanicHttpService) {
            throw new KernelException('PORT_TYPE', 'Mechanic HTTP service has a wrong type');
        }

        return $mechanicHttpService;
    }

    /**
     * Фасад из контейнера.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IMechanics Фасад.
     *
     * @throws KernelException Если тип чужой.
     */
    private function mechanics(IServiceLocator $serviceLocator): IMechanics
    {
        $mechanics = $serviceLocator->get(IMechanicContainer::class)->get(IMechanics::class);
        if (!$mechanics instanceof IMechanics) {
            throw new KernelException('PORT_TYPE', 'Mechanic HTTP requires IMechanics');
        }

        return $mechanics;
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
            throw new KernelException('PORT_TYPE', 'Mechanic HTTP requires IUserAccess');
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
            throw new KernelException('PORT_TYPE', 'Mechanic requires ISmartTableGateway');
        }

        return $smartTableGateway;
    }
}
