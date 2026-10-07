<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Interface\Service\IGameChronicles;
use Mifrial\Roleplay\Game\Repository\GameChronicleEntryRepository;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;

/**
 * Сборка летописи. Не одиннадцатый метод GamePortFactory.
 */
final class GameChroniclePortFactory
{
    /**
     * Создаёт порт.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IGameChronicles Фасад.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function create(IServiceLocator $serviceLocator): IGameChronicles
    {
        $gateway = $this->smartTableGateway($serviceLocator);
        $timeOffset = new GameTimeOffset();

        return new GameChronicles(
            new GameRepository($gateway),
            new GameMemberRepository($gateway),
            new GameCardAccess(
                new GameRepository($gateway),
                new GameMemberRepository($gateway),
                new GameInvitationRepository($gateway),
            ),
            new GameChronicleEntryRepository($gateway),
            $timeOffset,
        );
    }

    /**
     * HTTP летописи.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return GameChronicleHttp Сценарий.
     *
     * @throws KernelException Если нет шлюза или актора.
     */
    public function createHttp(IServiceLocator $serviceLocator): GameChronicleHttp
    {
        return new GameChronicleHttp(
            $this->userAccess($serviceLocator),
            $this->create($serviceLocator),
            new GameTimeOffset(),
        );
    }

    /**
     * Шлюз таблиц.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если порта нет.
     */
    private function smartTableGateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $gateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$gateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Game requires ISmartTableGateway');
        }

        return $gateway;
    }

    /**
     * Актор запроса.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Доступ.
     *
     * @throws KernelException Если порта нет.
     */
    private function userAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $userAccess = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Game HTTP requires IUserAccess');
        }

        return $userAccess;
    }
}
