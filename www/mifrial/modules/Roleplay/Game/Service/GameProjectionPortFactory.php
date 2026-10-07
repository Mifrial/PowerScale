<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Interface\Service\IGameProjections;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;

/**
 * Сборка проекций. Не метод GamePortFactory.
 */
final class GameProjectionPortFactory
{
    /**
     * Создаёт порт.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IGameProjections Фасад.
     *
     * @throws KernelException Если нет шлюза.
     */
    public function create(IServiceLocator $serviceLocator): IGameProjections
    {
        $gateway = $this->smartTableGateway($serviceLocator);

        return new GameProjections(
            $gateway,
            $this->games($serviceLocator),
            new GameCardAccess(
                new GameRepository($gateway),
                new GameMemberRepository($gateway),
                new GameInvitationRepository($gateway),
            ),
            $this->characters($serviceLocator),
            $this->memberships($serviceLocator),
            new GameCharacterProjectionMask(),
        );
    }

    /**
     * HTTP проекций.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameProjectionHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createHttp(IServiceLocator $serviceLocator): GameProjectionHttp
    {
        return new GameProjectionHttp($this->userAccess($serviceLocator), $this->create($serviceLocator));
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
     * Строка игры.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IGames Фасад.
     *
     * @throws KernelException Если порта нет.
     */
    private function games(IServiceLocator $serviceLocator): IGames
    {
        $games = $serviceLocator->get(IGameContainer::class)->get(IGames::class);
        if (!$games instanceof IGames) {
            throw new KernelException('PORT_TYPE', 'Game projection requires IGames');
        }

        return $games;
    }

    /**
     * Строки персонажа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IGameMemberships Фасад.
     *
     * @throws KernelException Если порта нет.
     */
    private function memberships(IServiceLocator $serviceLocator): IGameMemberships
    {
        $memberships = $serviceLocator->get(IGameContainer::class)->get(IGameMemberships::class);
        if (!$memberships instanceof IGameMemberships) {
            throw new KernelException('PORT_TYPE', 'Game projection requires IGameMemberships');
        }

        return $memberships;
    }

    /**
     * Actual персонажа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacters Фасад.
     *
     * @throws KernelException Если порта нет.
     */
    private function characters(IServiceLocator $serviceLocator): ICharacters
    {
        $characters = $serviceLocator->get(ICharacterContainer::class)->get(ICharacters::class);
        if (!$characters instanceof ICharacters) {
            throw new KernelException('PORT_TYPE', 'Game projection requires ICharacters');
        }

        return $characters;
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
            throw new KernelException('PORT_TYPE', 'Game projection requires IUserAccess');
        }

        return $userAccess;
    }
}
