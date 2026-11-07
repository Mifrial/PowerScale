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
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGames;

/**
 * Базовые порты ударного домена.
 */
final class GameStrikePorts
{
    /**
     * Создаёт доступ к портам.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return void
     */
    public function __construct(private readonly IServiceLocator $serviceLocator)
    {
    }

    /**
     * Шлюз таблиц.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если порта нет.
     */
    public function gateway(): ISmartTableGateway
    {
        $gateway = $this->serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$gateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Game requires ISmartTableGateway');
        }

        return $gateway;
    }

    /**
     * Менеджер событий.
     *
     * @return IEventManager Менеджер.
     *
     * @throws KernelException Если порта нет.
     */
    public function events(): IEventManager
    {
        $events = $this->serviceLocator->get(IEventContainer::class)->get(IEventManager::class);
        if (!$events instanceof IEventManager) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IEventManager');
        }

        return $events;
    }

    /**
     * Доступ пользователя.
     *
     * @return IUserAccess Доступ.
     *
     * @throws KernelException Если порта нет.
     */
    public function userAccess(): IUserAccess
    {
        $userAccess = $this->serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IUserAccess');
        }

        return $userAccess;
    }

    /**
     * Чаты.
     *
     * @return IChats Сервис чатов.
     *
     * @throws KernelException Если порта нет.
     */
    public function chats(): IChats
    {
        $chats = $this->serviceLocator->get(IChatContainer::class)->get(IChats::class);
        if (!$chats instanceof IChats) {
            throw new KernelException('PORT_TYPE', 'Chat service has a wrong type');
        }

        return $chats;
    }

    /**
     * Строка игры.
     *
     * @return IGames Фасад.
     *
     * @throws KernelException Если порта нет.
     */
    public function games(): IGames
    {
        $games = $this->serviceLocator->get(IGameContainer::class)->get(IGames::class);
        if (!$games instanceof IGames) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IGames');
        }

        return $games;
    }

    /**
     * Порт листа.
     *
     * @return ICharacterActualMutations Порт.
     *
     * @throws KernelException Если порта нет.
     */
    public function mutations(): ICharacterActualMutations
    {
        $mutations = $this->serviceLocator->get(ICharacterContainer::class)->get(ICharacterActualMutations::class);
        if (!$mutations instanceof ICharacterActualMutations) {
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacterActualMutations');
        }

        return $mutations;
    }
}
