<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ListQuery;
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
use Mifrial\Messages\Chat\Dto\NewGroupChat;
use Mifrial\Messages\Chat\Exception\ChatDuplicateException;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Schema\ChatSchema;
use Mifrial\Messages\Chat\Service\ChatInputNormalizer;
use Mifrial\Messages\Chat\Table\ChatMemberTable;
use Mifrial\Messages\Chat\Table\ChatMessageTable;
use Mifrial\Messages\Chat\Table\ChatTable;
use PHPUnit\Framework\TestCase;

final class ChatMysqlTest extends TestCase
{
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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Chat tests');
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
     * boot() грузит маршруты Chat; контейнер — первый get.
     *
     * @return void
     */
    public function testLazyLoadOnFirstGet(): void
    {
        self::assertInstanceOf(IChats::class, $this->chats());
    }

    /**
     * Двое → private, повтор тот же id, порядок аргументов любой.
     *
     * @return void
     */
    public function testPrivateIdempotent(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $chats = $this->chats();
        $firstId = $chats->addPrivate($alice, $bob);
        $secondId = $chats->addPrivate($bob, $alice);
        self::assertSame($firstId, $secondId);
        $chatRecord = $chats->getById($firstId);
        self::assertSame('private', $chatRecord->getType());
        self::assertSame('', $chatRecord->getName());
        self::assertGreaterThan(0, $chatRecord->getCreatedAt()->toUnix());
        self::assertGreaterThan(0, $chatRecord->getUpdatedAt()->toUnix());
        $memberIds = $chats->getMemberIds($firstId);
        sort($memberIds);
        self::assertSame([$alice, $bob], $memberIds);
    }

    /**
     * Self-private INVALID; нет user → CHAT_NOT_FOUND.
     *
     * @return void
     */
    public function testPrivateRejects(): void
    {
        $alice = $this->addUser('alice');
        $chats = $this->chats();
        try {
            $chats->addPrivate($alice, $alice);
            self::fail('self-private must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        try {
            $chats->addPrivate($alice, 999999);
            self::fail('missing user must fail');
        } catch (ChatNotFoundException $exception) {
            self::assertSame('CHAT_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Group имя; два group с NULL pair_key; getById чужого id находит.
     *
     * @return void
     */
    public function testGroupChats(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $carol = $this->addUser('carol');
        $chats = $this->chats();
        $normalizer = new ChatInputNormalizer();
        $firstGroupId = $chats->addGroup($normalizer->newGroupChat('Party', $alice, [$bob]));
        $secondGroupId = $chats->addGroup($normalizer->newGroupChat('Other', $alice, []));
        self::assertNotSame($firstGroupId, $secondGroupId);
        self::assertSame('Party', $chats->getById($firstGroupId)->getName());
        self::assertSame('group', $chats->getById($firstGroupId)->getType());
        self::assertSame([$alice], $chats->getMemberIds($secondGroupId));
        try {
            $chats->addGroup(NewGroupChat::fromNormalized([
                'name' => '   ',
                'creatorId' => $alice,
                'memberIds' => [],
            ]));
            self::fail('empty group name must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        $outsiderView = $chats->getById($firstGroupId);
        self::assertSame($firstGroupId, $outsiderView->getId());
        self::assertContains($firstGroupId, $chats->getChatIdsOfUser($bob));
        self::assertNotContains($firstGroupId, $chats->getChatIdsOfUser($carol));
    }

    /**
     * Дубль членства; private addMember INVALID; последний group INVALID.
     *
     * @return void
     */
    public function testMembershipRules(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $carol = $this->addUser('carol');
        $chats = $this->chats();
        $privateId = $chats->addPrivate($alice, $bob);
        $groupId = $chats->addGroup((new ChatInputNormalizer())->newGroupChat('G', $alice, []));
        try {
            $chats->addMember($privateId, $carol);
            self::fail('private addMember must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        $chats->addMember($groupId, $carol);
        try {
            $chats->addMember($groupId, $carol);
            self::fail('duplicate member must fail');
        } catch (ChatDuplicateException $exception) {
            self::assertSame('CHAT_DUPLICATE', $exception->getErrorCode());
        }

        $chats->removeMember($groupId, $carol);
        try {
            $chats->removeMember($groupId, $alice);
            self::fail('last member must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * send, страница, markRead, не член, пустой send, вложение, bounds.
     *
     * @return void
     */
    public function testMessagesAndRead(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $carol = $this->addUser('carol');
        $chats = $this->chats();
        $chatId = $chats->addPrivate($alice, $bob);
        $firstId = $chats->send($chatId, $alice, 'hi', []);
        $secondId = $chats->send($chatId, $bob, '', [['type' => 'roll', 'payload' => ['n' => 2]]]);
        $page = $chats->findMessagePage($chatId, $alice, 10, 0);
        self::assertSame(2, $page->getTotal());
        self::assertSame($secondId, $page->getItems()[0]->getId());
        self::assertSame($firstId, $page->getItems()[1]->getId());
        self::assertSame([['type' => 'roll', 'payload' => ['n' => 2]]], $page->getItems()[0]->getAttachments());
        $chats->markRead($chatId, $alice);
        self::assertSame($secondId, $this->lastReadMessageId($chatId, $alice));
        try {
            $chats->send($chatId, $carol, 'no', []);
            self::fail('non-member send must fail');
        } catch (ChatNotFoundException $exception) {
            self::assertSame('CHAT_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $chats->findMessagePage($chatId, $carol, 10, 0);
            self::fail('non-member page must fail');
        } catch (ChatNotFoundException $exception) {
            self::assertSame('CHAT_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $chats->send($chatId, $alice, '  ', []);
            self::fail('empty send must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        try {
            $chats->send($chatId, $alice, 'x', [['payload' => 1]]);
            self::fail('attachment without type must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        try {
            $chats->findMessagePage($chatId, $alice, 0, 0);
            self::fail('limit 0 must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }

        try {
            $chats->findMessagePage($chatId, $alice, 10, -1);
            self::fail('negative offset must fail');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * boot() и ленивый get; схемы User затем Chat.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectChat(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $moduleManager = $application->getModuleManager();
        self::assertTrue($this->isChatLoaded($moduleManager->getLoadedModules()));
        self::assertFalse($moduleManager->hasContainer('Messages', 'Chat'));
        self::assertArrayHasKey('chat.getChats', $moduleManager->getRoutes());
        $chatContainer = $application->getLocator()->get(IChatContainer::class);
        self::assertTrue($moduleManager->hasContainer('Messages', 'Chat'));
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
     * Модуль Messages/Chat уже в loaded.
     *
     * @param array<int, array{group: string, name: string, config: array<string, mixed>}> $loadedModules Список.
     *
     * @return bool true, если Chat загружен.
     */
    private function isChatLoaded(array $loadedModules): bool
    {
        foreach ($loadedModules as $loadedModule) {
            if ($loadedModule['group'] === 'Messages' && $loadedModule['name'] === 'Chat') {
                return true;
            }
        }

        return false;
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
     * last_read_message_id членства.
     *
     * @param int $chatId Чат.
     * @param int $userId Учётка.
     *
     * @return mixed Значение колонки.
     */
    private function lastReadMessageId(int $chatId, int $userId): mixed
    {
        $row = $this->smartTableGateway()->open(ChatMemberTable::class)->records()->getUnique(ListQuery::fromOptions([
            'filter' => [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ],
            'limit' => 1,
            'select' => ['last_read_message_id'],
        ]));
        self::assertIsArray($row);

        return $row['last_read_message_id'];
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
