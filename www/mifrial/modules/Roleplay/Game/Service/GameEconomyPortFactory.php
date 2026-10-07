<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Container\IEventContainer;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGameEconomy;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;

/**
 * Сборка экономики. Не одиннадцатый метод GamePortFactory.
 */
final class GameEconomyPortFactory
{
    /**
     * Создаёт порт.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IGameEconomy Фасад.
     *
     * @throws KernelException Если нет шлюза или порта листа.
     */
    public function create(IServiceLocator $serviceLocator): IGameEconomy
    {
        $gateway = $this->smartTableGateway($serviceLocator);
        $events = $this->events($serviceLocator);
        GameDeliveryListener::register($events, new GameDelivery(
            $gateway,
            $this->games($serviceLocator),
            new GameCardAccess(
                new GameRepository($gateway),
                new GameMemberRepository($gateway),
                new GameInvitationRepository($gateway),
            ),
        ));

        return new GameEconomy(
            $gateway,
            $this->games($serviceLocator),
            $this->memberships($serviceLocator),
            new GameMemberRepository($gateway),
            new GameCardAccess(
                new GameRepository($gateway),
                new GameMemberRepository($gateway),
                new GameInvitationRepository($gateway),
            ),
            $this->mutations($serviceLocator),
            $this->characters($serviceLocator),
            $events,
        );
    }

    /**
     * HTTP экономики.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return GameEconomyHttp Сценарий.
     *
     * @throws KernelException Если нет порта.
     */
    public function createHttp(IServiceLocator $serviceLocator): GameEconomyHttp
    {
        return new GameEconomyHttp($this->userAccess($serviceLocator), $this->create($serviceLocator));
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
            throw new KernelException('PORT_TYPE', 'Game economy requires IGames');
        }

        return $games;
    }

    /**
     * Membership.
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
            throw new KernelException('PORT_TYPE', 'Game economy requires IGameMemberships');
        }

        return $memberships;
    }

    /**
     * Порт мутации листа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterActualMutations Порт.
     *
     * @throws KernelException Если порта нет.
     */
    private function mutations(IServiceLocator $serviceLocator): ICharacterActualMutations
    {
        $mutations = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterActualMutations::class);
        if (!$mutations instanceof ICharacterActualMutations) {
            throw new KernelException('PORT_TYPE', 'Game economy requires ICharacterActualMutations');
        }

        return $mutations;
    }

    /**
     * Строки персонажа.
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
            throw new KernelException('PORT_TYPE', 'Game economy requires ICharacters');
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
    private function events(IServiceLocator $serviceLocator): IEventManager
    {
        $events = $serviceLocator->get(IEventContainer::class)->get(IEventManager::class);
        if (!$events instanceof IEventManager) {
            throw new KernelException('PORT_TYPE', 'Game economy requires IEventManager');
        }

        return $events;
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
            throw new KernelException('PORT_TYPE', 'Game economy requires IUserAccess');
        }

        return $userAccess;
    }
}
