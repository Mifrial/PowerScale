<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
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
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Repository\ChatMemberRepository;
use Mifrial\Messages\Chat\Schema\ChatSchema;
use Mifrial\Messages\Chat\Service\ChatInputNormalizer;
use Mifrial\Messages\Chat\Table\ChatMemberTable;
use Mifrial\Messages\Chat\Table\ChatMessageTable;
use Mifrial\Messages\Chat\Table\ChatTable;
use PHPUnit\Framework\TestCase;

final class ChatHttpMysqlTest extends TestCase
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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Chat HTTP tests');
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
     * Нет актора — AUTH_REQUIRED; пустой inbox — [].
     *
     * @return void
     */
    public function testAuthRequiredAndEmptyInbox(): void
    {
        $payload = $this->dispatch('chat.getChats', null);
        self::assertFalse($payload['success']);
        self::assertSame('AUTH_REQUIRED', $payload['error']['code']);

        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $empty = $this->dispatch('chat.getChats', null);
        self::assertTrue($empty['success']);
        self::assertSame([], $empty['data']);
    }

    /**
     * Свои чаты, чужой inbox пуст, JSON без lastMessage.
     *
     * @return void
     */
    public function testInboxJson(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $carol = $this->addUser('carol');
        $privateId = $this->chats()->addPrivate($alice, $bob);
        $groupId = $this->chats()->addGroup(
            (new ChatInputNormalizer())->newGroupChat('Party', $alice, [$bob]),
        );
        $this->setActor($alice);
        $inbox = $this->dispatch('chat.getChats', null);
        self::assertTrue($inbox['success']);
        self::assertCount(2, $inbox['data']);
        $byId = [];
        foreach ($inbox['data'] as $chatView) {
            $byId[$chatView['id']] = $chatView;
        }

        self::assertSame('private', $byId[$privateId]['type']);
        self::assertSame('', $byId[$privateId]['name']);
        self::assertSame(0, $byId[$privateId]['unreadCount']);
        self::assertNull($byId[$privateId]['lastReadMessageId']);
        self::assertIsInt($byId[$privateId]['lastMessageAt']);
        self::assertArrayNotHasKey('lastMessage', $byId[$privateId]);
        self::assertSame('group', $byId[$groupId]['type']);
        self::assertSame('Party', $byId[$groupId]['name']);
        $memberIds = [];
        foreach ($byId[$privateId]['members'] as $memberView) {
            $memberIds[] = $memberView['userId'];
            self::assertSame('member', $memberView['status']);
            self::assertIsInt($memberView['joinedAt']);
        }

        sort($memberIds);
        self::assertSame([$alice, $bob], $memberIds);
        $this->setActor($carol);
        $carolInbox = $this->dispatch('chat.getChats', null);
        self::assertTrue($carolInbox['success']);
        self::assertSame([], $carolInbox['data']);
    }

    /**
     * addPrivate пара и повтор; addGroup имя; self INVALID; нет user NOT_FOUND.
     *
     * @return void
     */
    public function testCreatePrivateAndGroup(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $this->setActor($alice);
        $created = $this->dispatch('chat.addPrivate', ['userId' => $bob]);
        self::assertTrue($created['success']);
        self::assertSame('private', $created['data']['type']);
        self::assertSame('', $created['data']['name']);
        $privateId = $created['data']['id'];
        $again = $this->dispatch('chat.addPrivate', ['userId' => $bob]);
        self::assertTrue($again['success']);
        self::assertSame($privateId, $again['data']['id']);
        $this->assertError('CHAT_INVALID', $this->dispatch('chat.addPrivate', ['userId' => $alice]));
        $this->assertError('CHAT_NOT_FOUND', $this->dispatch('chat.addPrivate', ['userId' => 999999]));
        $this->assertError('INVALID_PARAMS', $this->dispatch('chat.addPrivate', [
            'userId' => $bob,
            'extra' => 1,
        ]));
        $group = $this->dispatch('chat.addGroup', ['name' => '  Party  ', 'memberIds' => [$bob]]);
        self::assertTrue($group['success']);
        self::assertSame('group', $group['data']['type']);
        self::assertSame('Party', $group['data']['name']);
        $solo = $this->dispatch('chat.addGroup', ['name' => 'Solo']);
        self::assertTrue($solo['success']);
        self::assertSame('group', $solo['data']['type']);
        $this->assertError('CHAT_INVALID', $this->dispatch('chat.addGroup', ['name' => '   ']));
        $this->assertError('CHAT_NOT_FOUND', $this->dispatch('chat.addGroup', [
            'name' => 'X',
            'memberIds' => [999999],
        ]));
    }

    /**
     * Inbox не отдаёт type вне private/group.
     *
     * @return void
     */
    public function testInboxOmitsDonorType(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $this->chats()->addPrivate($alice, $bob);
        $gateway = $this->smartTableGateway();
        $gameId = $gateway->open(ChatTable::class)->records()->add([
            'type' => 'game',
            'name' => 'Scene',
            'pair_key' => 'donor-game-1',
        ]);
        (new ChatMemberRepository($gateway->open(ChatMemberTable::class)->records()))->add($gameId, $alice);
        $this->setActor($alice);
        $inbox = $this->dispatch('chat.getChats', null);
        self::assertTrue($inbox['success']);
        self::assertCount(1, $inbox['data']);
        self::assertSame('private', $inbox['data'][0]['type']);
    }

    /**
     * send, страница, markRead, не член, лишние ключи.
     *
     * @return void
     */
    public function testMessagesAndRejects(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $carol = $this->addUser('carol');
        $chatId = $this->chats()->addPrivate($alice, $bob);
        $this->setActor($alice);
        $sent = $this->dispatch('chat.sendMessage', [
            'chatId' => $chatId,
            'content' => 'hi',
        ]);
        self::assertTrue($sent['success']);
        self::assertSame($chatId, $sent['data']['chatId']);
        self::assertSame($alice, $sent['data']['userId']);
        self::assertSame('alice', $sent['data']['username']);
        self::assertSame('hi', $sent['data']['content']);
        self::assertSame([], $sent['data']['attachments']);
        self::assertSame(['all' => true], $sent['data']['visibility']);
        self::assertIsInt($sent['data']['createdAt']);
        self::assertSame($sent['data']['createdAt'], $sent['data']['updatedAt']);
        $messageId = $sent['data']['id'];
        $page = $this->dispatch('chat.findMessagePage', [
            'chatId' => $chatId,
            'limit' => 10,
            'offset' => 0,
        ]);
        self::assertTrue($page['success']);
        self::assertSame(1, $page['data']['total']);
        self::assertSame($messageId, $page['data']['items'][0]['id']);
        $read = $this->dispatch('chat.markChatRead', ['chatId' => $chatId]);
        self::assertTrue($read['success']);
        self::assertNull($read['data']);
        $inbox = $this->dispatch('chat.getChats', null);
        self::assertSame($messageId, $inbox['data'][0]['lastReadMessageId']);
        $this->assertError('INVALID_PARAMS', $this->dispatch('chat.sendMessage', [
            'chatId' => $chatId,
            'content' => 'x',
            'speaker' => 'npc',
        ]));
        $this->assertError('INVALID_PARAMS', $this->dispatch('chat.findMessagePage', [
            'chatId' => $chatId,
            'limit' => 10,
            'offset' => 0,
            'beforeId' => 1,
        ]));
        $this->assertError('INVALID_PARAMS', $this->dispatch('chat.getChats', ['since' => 1]));
        $this->setActor($carol);
        $this->assertError('CHAT_NOT_FOUND', $this->dispatch('chat.sendMessage', [
            'chatId' => $chatId,
            'content' => 'no',
        ]));
        $this->assertError('CHAT_NOT_FOUND', $this->dispatch('chat.findMessagePage', [
            'chatId' => $chatId,
            'limit' => 10,
            'offset' => 0,
        ]));
        $this->assertError('CHAT_NOT_FOUND', $this->dispatch('chat.markChatRead', ['chatId' => $chatId]));
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
     * Ставит актора без ключей чата.
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
     * Ошибка конверта.
     *
     * @param string $errorCode Код.
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
     * @return IApplication Приложение.
     */
    private function application(): IApplication
    {
        self::assertInstanceOf(IApplication::class, $this->application);

        return $this->application;
    }

    /**
     * Контекст после setUp.
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
