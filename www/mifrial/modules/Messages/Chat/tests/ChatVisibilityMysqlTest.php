<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Core\User\Tests\UserMysqlTables;
use Mifrial\Messages\Chat\Dto\MessageAudience;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Repository\ChatMessageRepository;
use Mifrial\Messages\Chat\Repository\ChatRepository;
use Mifrial\Messages\Chat\Schema\ChatSchema;
use Mifrial\Messages\Chat\Table\ChatMemberTable;
use Mifrial\Messages\Chat\Table\ChatMessageTable;
use Mifrial\Messages\Chat\Table\ChatTable;
use PHPUnit\Framework\TestCase;

final class ChatVisibilityMysqlTest extends TestCase
{
    private ?IApplication $application = null;

    private ?IRequestContext $requestContext = null;

    private ?IChats $chats = null;

    private ?IUserAccounts $userAccounts = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    /**
     * Подключается к MySQL или skip; сносит таблицы Chat и User.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectChat();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Chat visibility tests');
        }

        $this->dropChatAndUserTables();
        $this->installSchemas();
    }

    /**
     * Сносит Chat, затем User.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropChatAndUserTables();
    }

    /**
     * Шёпот соседа не в ленте, unread и preview; автор видит.
     *
     * @return void
     */
    public function testWhisperHiddenFromNeighbor(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $chatId = $this->chats()->addPrivate($alice, $bob);
        $this->setActor($alice);
        $visible = $this->dispatch('chat.sendMessage', [
            'chatId' => $chatId,
            'content' => 'hello',
        ]);
        $whisper = $this->dispatch('chat.sendMessage', [
            'chatId' => $chatId,
            'content' => 'secret',
            'visibility' => ['all' => false, 'forUsers' => []],
        ]);
        self::assertTrue($visible['success']);
        self::assertTrue($whisper['success']);
        self::assertSame(['all' => false, 'forUsers' => []], $whisper['data']['visibility']);
        $alicePage = $this->dispatch('chat.findMessagePage', [
            'chatId' => $chatId,
            'limit' => 10,
            'offset' => 0,
        ]);
        self::assertSame(2, $alicePage['data']['total']);
        $aliceInbox = $this->dispatch('chat.getChats', null);
        self::assertSame(2, $aliceInbox['data'][0]['unreadCount']);
        self::assertSame('secret', $aliceInbox['data'][0]['lastMessage']);
        $this->setActor($bob);
        $bobPage = $this->dispatch('chat.findMessagePage', [
            'chatId' => $chatId,
            'limit' => 10,
            'offset' => 0,
        ]);
        self::assertSame(1, $bobPage['data']['total']);
        self::assertSame('hello', $bobPage['data']['items'][0]['content']);
        $bobInbox = $this->dispatch('chat.getChats', null);
        self::assertSame(1, $bobInbox['data'][0]['unreadCount']);
        self::assertSame('hello', $bobInbox['data'][0]['lastMessage']);
        self::assertSame($visible['data']['createdAt'], $bobInbox['data'][0]['lastMessageAt']);
        $this->dispatch('chat.markChatRead', ['chatId' => $chatId]);
        $bobAfterRead = $this->dispatch('chat.getChats', null);
        self::assertSame(0, $bobAfterRead['data'][0]['unreadCount']);
        self::assertSame($visible['data']['id'], $bobAfterRead['data'][0]['lastReadMessageId']);
    }

    /**
     * Нет видимых → нет lastMessage; lastMessageAt = created_at чата, не updated_at.
     *
     * @return void
     */
    public function testInboxPreviewFallsBackToChatCreatedAt(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $chatId = $this->chats()->addPrivate($alice, $bob);
        $createdAt = $this->chats()->getById($chatId)->getCreatedAt()->toUnix();
        $this->setActor($alice);
        $whisper = $this->dispatch('chat.sendMessage', [
            'chatId' => $chatId,
            'content' => 'secret',
            'visibility' => ['all' => false, 'forUsers' => []],
        ]);
        self::assertTrue($whisper['success']);
        $this->chatRepository()->updateUpdatedAt($chatId, DateTime::fromUnix($createdAt + 10000));
        $this->setActor($bob);
        $bobInbox = $this->dispatch('chat.getChats', null);
        self::assertSame(0, $bobInbox['data'][0]['unreadCount']);
        self::assertArrayNotHasKey('lastMessage', $bobInbox['data'][0]);
        self::assertSame($createdAt, $bobInbox['data'][0]['lastMessageAt']);
    }

    /**
     * Шёпот соседа не в getUpdatedSince.
     *
     * @return void
     */
    public function testWhisperHiddenFromNeighborUpdatedSince(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $chatId = $this->chats()->addPrivate($alice, $bob);
        $sentAt = DateTime::fromUnix(40);
        $this->messageRepository()->add(
            $chatId,
            $alice,
            'secret',
            [],
            $sentAt,
            MessageAudience::forUsers([]),
        );
        $horizon = DateTime::fromUnix(50);
        $aliceRows = $this->messageRepository()->getUpdatedSince([$chatId], $alice, 0, 0, $horizon, 10);
        $bobRows = $this->messageRepository()->getUpdatedSince([$chatId], $bob, 0, 0, $horizon, 10);
        self::assertCount(1, $aliceRows);
        self::assertSame('secret', $aliceRows[0]->getContent());
        self::assertSame([], $bobRows);
    }

    /**
     * Update: автор, не автор NOT_FOUND, forRole INVALID.
     *
     * @return void
     */
    public function testUpdateVisibility(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $chatId = $this->chats()->addPrivate($alice, $bob);
        $this->setActor($alice);
        $sent = $this->dispatch('chat.sendMessage', [
            'chatId' => $chatId,
            'content' => 'hi',
        ]);
        $messageId = $sent['data']['id'];
        $updated = $this->dispatch('chat.updateMessageVisibility', [
            'chatId' => $chatId,
            'messageId' => $messageId,
            'visibility' => ['all' => false, 'forUsers' => [$bob]],
        ]);
        self::assertTrue($updated['success']);
        self::assertSame(['all' => false, 'forUsers' => [$bob]], $updated['data']['visibility']);
        $this->assertError('CHAT_INVALID', $this->dispatch('chat.updateMessageVisibility', [
            'chatId' => $chatId,
            'messageId' => $messageId,
            'visibility' => ['all' => false, 'forRole' => 'gm'],
        ]));
        $this->assertError('CHAT_INVALID', $this->dispatch('chat.sendMessage', [
            'chatId' => $chatId,
            'content' => 'x',
            'visibility' => ['all' => true, 'forUsers' => []],
        ]));
        $this->setActor($bob);
        $this->assertError('CHAT_NOT_FOUND', $this->dispatch('chat.updateMessageVisibility', [
            'chatId' => $chatId,
            'messageId' => $messageId,
        ]));
        $openAgain = $this->dispatch('chat.updateMessageVisibility', [
            'chatId' => $chatId,
            'messageId' => $messageId,
            'visibility' => ['all' => true],
        ]);
        self::assertFalse($openAgain['success']);
        $this->setActor($alice);
        $restored = $this->dispatch('chat.updateMessageVisibility', [
            'chatId' => $chatId,
            'messageId' => $messageId,
        ]);
        self::assertTrue($restored['success']);
        self::assertSame(['all' => true], $restored['data']['visibility']);
        $this->chats()->send($chatId, $alice, 'facade', [], MessageAudience::forUsers([]));
        $this->setActor($bob);
        $page = $this->dispatch('chat.findMessagePage', [
            'chatId' => $chatId,
            'limit' => 10,
            'offset' => 0,
        ]);
        self::assertSame(1, $page['data']['total']);
    }

    /**
     * boot() и ленивый контейнер Chat; схемы User затем Chat.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectChat(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $this->application = $application;
        $kernelContainer = $application->getLocator()->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;
        $chatContainer = $application->getLocator()->get(IChatContainer::class);
        $chats = $chatContainer->get(IChats::class);
        self::assertInstanceOf(IChats::class, $chats);
        $this->chats = $chats;
        $userContainer = $application->getLocator()->get(IUserContainer::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        $this->userAccounts = $userAccounts;
        $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->smartTableGateway = $smartTableGateway;
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            self::markTestSkipped('test connection is not Illuminate');
        }

        $databaseConnection->ping();
    }

    /**
     * Ставит User, затем Chat.
     *
     * @return void
     */
    private function installSchemas(): void
    {
        $gateway = $this->smartTableGateway();
        (new UserSchema(
            $gateway->open(UserTable::class)->schema(),
            $gateway->open(UserGroupTable::class)->schema(),
            $gateway->open(UserGroupMemberTable::class)->schema(),
        ))->install();
        (new ChatSchema(
            $gateway->open(ChatTable::class)->schema(),
            $gateway->open(ChatMemberTable::class)->schema(),
            $gateway->open(ChatMessageTable::class)->schema(),
        ))->install();
    }

    /**
     * Сносит Chat и User.
     *
     * @return void
     */
    private function dropChatAndUserTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        ChatMysqlTables::drop($this->smartTableGateway);
        UserMysqlTables::drop($this->smartTableGateway);
    }

    /**
     * Учётка с login=name.
     *
     * @param string $login Логин.
     *
     * @return int Id.
     */
    private function addUser(string $login): int
    {
        return $this->userAccounts()->addFromInput([
            'login' => $login,
            'name' => $login,
        ]);
    }

    /**
     * Ставит актора HTTP.
     *
     * @param int $userId Учётка.
     *
     * @return void
     */
    private function setActor(int $userId): void
    {
        $this->requestContext()->setActor(new RequestActor($userId, [], false));
    }

    /**
     * Вызывает action через приложение.
     *
     * @param string $action Код.
     * @param mixed $payload Тело.
     *
     * @return array<string, mixed> Конверт.
     */
    private function dispatch(string $action, mixed $payload): array
    {
        return $this->application()->dispatch($action, $payload)->toArray();
    }

    /**
     * Код ошибки action.
     *
     * @param string $errorCode Ожидание.
     * @param array<string, mixed> $payload Конверт.
     *
     * @return void
     */
    private function assertError(string $errorCode, array $payload): void
    {
        self::assertFalse($payload['success']);
        self::assertSame($errorCode, $payload['error']['code']);
    }

    /**
     * Приложение после setUp.
     *
     * @return IApplication Ядро.
     */
    private function application(): IApplication
    {
        self::assertInstanceOf(IApplication::class, $this->application);

        return $this->application;
    }

    /**
     * Контекст запроса после setUp.
     *
     * @return IRequestContext Контекст.
     */
    private function requestContext(): IRequestContext
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);

        return $this->requestContext;
    }

    /**
     * Фасад чатов после setUp.
     *
     * @return IChats Фасад.
     */
    private function chats(): IChats
    {
        self::assertInstanceOf(IChats::class, $this->chats);

        return $this->chats;
    }

    /**
     * Фасад учётки после setUp.
     *
     * @return IUserAccounts Фасад.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Репозиторий сообщений после setUp.
     *
     * @return ChatMessageRepository Репозиторий.
     */
    private function messageRepository(): ChatMessageRepository
    {
        return new ChatMessageRepository(
            $this->smartTableGateway()->open(ChatMessageTable::class)->records(),
        );
    }

    /**
     * Репозиторий чатов после setUp.
     *
     * @return ChatRepository Репозиторий.
     */
    private function chatRepository(): ChatRepository
    {
        return new ChatRepository(
            $this->smartTableGateway()->open(ChatTable::class)->records(),
        );
    }

    /**
     * Шлюз после setUp.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }
}
