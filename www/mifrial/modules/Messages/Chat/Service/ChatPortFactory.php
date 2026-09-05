<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Repository\ChatMemberRepository;
use Mifrial\Messages\Chat\Repository\ChatMessageRepository;
use Mifrial\Messages\Chat\Repository\ChatRepository;
use Mifrial\Messages\Chat\Table\ChatMemberTable;
use Mifrial\Messages\Chat\Table\ChatMessageTable;
use Mifrial\Messages\Chat\Table\ChatTable;

/**
 * Сборка фасада, HTTP и SSE чатов.
 */
final class ChatPortFactory
{
    /**
     * Создаёт фасад.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return IChats Фасад.
     *
     * @throws KernelException Если нет шлюза или IUserAccounts.
     */
    public function create(IServiceLocator $serviceLocator): IChats
    {
        $repositories = $this->repositories($serviceLocator);

        return new Chats(
            $repositories['chat'],
            $repositories['member'],
            $repositories['message'],
            $this->userAccounts($serviceLocator),
        );
    }

    /**
     * Создаёт HTTP-сценарий.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ChatHttpService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function createHttp(IServiceLocator $serviceLocator): ChatHttpService
    {
        $repositories = $this->repositories($serviceLocator);

        return new ChatHttpService(
            $this->userAccess($serviceLocator),
            $this->chats($serviceLocator),
            $this->userAccounts($serviceLocator),
            new ChatViewAssembler($repositories['chat'], $repositories['member'], $repositories['message']),
            $repositories['message'],
        );
    }

    /**
     * Берёт HTTP-сценарий из контейнера Chat.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ChatHttpService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function fromContainer(IServiceLocator $serviceLocator): ChatHttpService
    {
        $chatContainer = $serviceLocator->get(IChatContainer::class);
        $chatHttpService = $chatContainer->get(ChatHttpService::class);
        if (!$chatHttpService instanceof ChatHttpService) {
            throw new KernelException('PORT_TYPE', 'Chat HTTP service has a wrong type');
        }

        return $chatHttpService;
    }

    /**
     * Создаёт сценарий SSE.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ChatSseService Сценарий.
     *
     * @throws KernelException Если порт неверного типа.
     */
    public function createSse(IServiceLocator $serviceLocator): ChatSseService
    {
        $repositories = $this->repositories($serviceLocator);

        return new ChatSseService(
            $this->userAccess($serviceLocator),
            $this->chats($serviceLocator),
            new ChatViewAssembler($repositories['chat'], $repositories['member'], $repositories['message']),
            $repositories['message'],
            $this->userAccounts($serviceLocator),
            new SystemSseClock(),
        );
    }

    /**
     * Берёт SSE-сценарий из контейнера Chat.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ChatSseService Сценарий.
     *
     * @throws KernelException Если тип неверен.
     */
    public function fromContainerSse(IServiceLocator $serviceLocator): ChatSseService
    {
        $chatContainer = $serviceLocator->get(IChatContainer::class);
        $chatSseService = $chatContainer->get(ChatSseService::class);
        if (!$chatSseService instanceof ChatSseService) {
            throw new KernelException('PORT_TYPE', 'Chat SSE service has a wrong type');
        }

        return $chatSseService;
    }

    /**
     * Учётки через контейнер User.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccounts Фасад учётки.
     *
     * @throws KernelException Если тип порта чужой.
     */
    private function userAccounts(IServiceLocator $serviceLocator): IUserAccounts
    {
        $userAccounts = $serviceLocator->get(IUserContainer::class)->get(IUserAccounts::class);
        if (!$userAccounts instanceof IUserAccounts) {
            throw new KernelException('PORT_TYPE', 'Chat requires IUserAccounts');
        }

        return $userAccounts;
    }

    /**
     * Guard через контейнер User.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IUserAccess Guard.
     *
     * @throws KernelException Если тип порта чужой.
     */
    private function userAccess(IServiceLocator $serviceLocator): IUserAccess
    {
        $userAccess = $serviceLocator->get(IUserContainer::class)->get(IUserAccess::class);
        if (!$userAccess instanceof IUserAccess) {
            throw new KernelException('PORT_TYPE', 'Chat HTTP requires IUserAccess');
        }

        return $userAccess;
    }

    /**
     * Фасад из контейнера Chat.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IChats Фасад.
     *
     * @throws KernelException Если тип порта чужой.
     */
    private function chats(IServiceLocator $serviceLocator): IChats
    {
        $chats = $serviceLocator->get(IChatContainer::class)->get(IChats::class);
        if (!$chats instanceof IChats) {
            throw new KernelException('PORT_TYPE', 'Chat HTTP requires IChats');
        }

        return $chats;
    }

    /**
     * Репозитории модуля.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return array{
     *     chat: ChatRepository,
     *     member: ChatMemberRepository,
     *     message: ChatMessageRepository
     * } Репозитории.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function repositories(IServiceLocator $serviceLocator): array
    {
        $smartTableGateway = $this->smartTableGateway($serviceLocator);

        return [
            'chat' => new ChatRepository($smartTableGateway->open(ChatTable::class)->records()),
            'member' => new ChatMemberRepository($smartTableGateway->open(ChatMemberTable::class)->records()),
            'message' => new ChatMessageRepository($smartTableGateway->open(ChatMessageTable::class)->records()),
        ];
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
        $smartTableContainer = $serviceLocator->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Chat requires ISmartTableGateway');
        }

        return $smartTableGateway;
    }
}
