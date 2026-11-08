<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Interface\Service\IChatTypeRegistry;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;

/**
 * Чаты игры из контейнера Chat.
 */
final class GameChatPortFactory
{
    /**
     * Создаёт сборку.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param ISmartTableGateway $gateway Шлюз таблиц.
     *
     * @return void
     */
    public function __construct(
        private readonly IServiceLocator $serviceLocator,
        private readonly ISmartTableGateway $gateway,
    ) {
    }

    /**
     * Чаты через реестр контейнера Chat.
     *
     * @return IChats Чаты.
     *
     * @throws KernelException Если порта нет.
     */
    public function chats(): IChats
    {
        $chats = $this->container()->get(IChats::class);
        if (!$chats instanceof IChats) {
            throw new KernelException('PORT_TYPE', 'Game requires IChats');
        }

        return $chats;
    }

    /**
     * Чаты донора игры.
     *
     * @return GameDonorChats Чаты строк.
     *
     * @throws KernelException Если порта нет.
     */
    public function donorChats(): GameDonorChats
    {
        $chatContainer = $this->container();
        $registry = $chatContainer->get(IChatTypeRegistry::class);
        $chats = $chatContainer->get(IChats::class);
        if (!$registry instanceof IChatTypeRegistry || !$chats instanceof IChats) {
            throw new KernelException('PORT_TYPE', 'Game requires chat ports');
        }

        return new GameDonorChats($registry, $chats, new GameCharacterRepository($this->gateway));
    }

    /**
     * Контейнер чатов.
     *
     * @return IChatContainer Контейнер.
     *
     * @throws KernelException Если порта нет.
     */
    private function container(): IChatContainer
    {
        $chatContainer = $this->serviceLocator->get(IChatContainer::class);
        if (!$chatContainer instanceof IChatContainer) {
            throw new KernelException('PORT_TYPE', 'Game requires IChatContainer');
        }

        return $chatContainer;
    }
}
