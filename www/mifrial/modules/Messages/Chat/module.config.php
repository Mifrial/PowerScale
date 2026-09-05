<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Messages\Chat\Action\AddGroupChatAction;
use Mifrial\Messages\Chat\Action\AddPrivateChatAction;
use Mifrial\Messages\Chat\Action\FindMessagePageAction;
use Mifrial\Messages\Chat\Action\GetChatsAction;
use Mifrial\Messages\Chat\Action\MarkChatReadAction;
use Mifrial\Messages\Chat\Action\SendMessageAction;
use Mifrial\Messages\Chat\Action\UpdateMessageVisibilityAction;
use Mifrial\Messages\Chat\Container\ChatContainer;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Service\ChatHttpService;
use Mifrial\Messages\Chat\Service\ChatPortFactory;
use Mifrial\Messages\Chat\Service\ChatSseService;
use Mifrial\Messages\Chat\Setup\ChatModuleSetup;

return [
    'container' => ChatContainer::class,
    'locator' => IChatContainer::class,
    'setup' => ChatModuleSetup::class,
    'ports' => [
        IChats::class => static function (IServiceLocator $serviceLocator): IChats {
            return (new ChatPortFactory())->create($serviceLocator);
        },
        ChatHttpService::class => static function (IServiceLocator $serviceLocator): ChatHttpService {
            return (new ChatPortFactory())->createHttp($serviceLocator);
        },
        ChatSseService::class => static function (IServiceLocator $serviceLocator): ChatSseService {
            return (new ChatPortFactory())->createSse($serviceLocator);
        },
        GetChatsAction::class => static function (IServiceLocator $serviceLocator): GetChatsAction {
            return new GetChatsAction((new ChatPortFactory())->fromContainer($serviceLocator));
        },
        FindMessagePageAction::class => static function (
            IServiceLocator $serviceLocator,
        ): FindMessagePageAction {
            return new FindMessagePageAction((new ChatPortFactory())->fromContainer($serviceLocator));
        },
        SendMessageAction::class => static function (IServiceLocator $serviceLocator): SendMessageAction {
            return new SendMessageAction((new ChatPortFactory())->fromContainer($serviceLocator));
        },
        MarkChatReadAction::class => static function (IServiceLocator $serviceLocator): MarkChatReadAction {
            return new MarkChatReadAction((new ChatPortFactory())->fromContainer($serviceLocator));
        },
        UpdateMessageVisibilityAction::class => static function (
            IServiceLocator $serviceLocator,
        ): UpdateMessageVisibilityAction {
            return new UpdateMessageVisibilityAction((new ChatPortFactory())->fromContainer($serviceLocator));
        },
        AddPrivateChatAction::class => static function (IServiceLocator $serviceLocator): AddPrivateChatAction {
            return new AddPrivateChatAction((new ChatPortFactory())->fromContainer($serviceLocator));
        },
        AddGroupChatAction::class => static function (IServiceLocator $serviceLocator): AddGroupChatAction {
            return new AddGroupChatAction((new ChatPortFactory())->fromContainer($serviceLocator));
        },
    ],
    'routes' => [
        'chat.getChats' => [
            'handler' => GetChatsAction::class,
            'csrf' => true,
        ],
        'chat.findMessagePage' => [
            'handler' => FindMessagePageAction::class,
            'csrf' => true,
        ],
        'chat.sendMessage' => [
            'handler' => SendMessageAction::class,
            'csrf' => true,
        ],
        'chat.markChatRead' => [
            'handler' => MarkChatReadAction::class,
            'csrf' => true,
        ],
        'chat.updateMessageVisibility' => [
            'handler' => UpdateMessageVisibilityAction::class,
            'csrf' => true,
        ],
        'chat.addPrivate' => [
            'handler' => AddPrivateChatAction::class,
            'csrf' => true,
        ],
        'chat.addGroup' => [
            'handler' => AddGroupChatAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
