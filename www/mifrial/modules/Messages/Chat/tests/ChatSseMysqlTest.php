<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Http\SseEmitter;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Core\User\Tests\UserMysqlTables;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Repository\ChatMemberRepository;
use Mifrial\Messages\Chat\Repository\ChatMessageRepository;
use Mifrial\Messages\Chat\Repository\ChatRepository;
use Mifrial\Messages\Chat\Schema\ChatSchema;
use Mifrial\Messages\Chat\Service\ChatSseService;
use Mifrial\Messages\Chat\Service\ChatViewAssembler;
use Mifrial\Messages\Chat\Table\ChatMemberTable;
use Mifrial\Messages\Chat\Table\ChatMessageTable;
use Mifrial\Messages\Chat\Table\ChatTable;
use PHPUnit\Framework\TestCase;

final class ChatSseMysqlTest extends TestCase
{
    private ?IRequestContext $requestContext = null;

    private ?IChats $chats = null;

    private ?IUserAccounts $userAccounts = null;

    private ?IUserAccess $userAccess = null;

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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Chat SSE tests');
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
     * Нет актора — AUTH_REQUIRED до потока.
     *
     * @return void
     */
    public function testAuthRequired(): void
    {
        try {
            $this->runSse($this->query([]), new FakeSseClock(10, 0));
            self::fail('AUTH_REQUIRED expected');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_REQUIRED', $exception->getErrorCode());
        }
    }

    /**
     * Live: hello с now, без старых сообщений.
     *
     * @return void
     */
    public function testLiveHelloDoesNotDump(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $chatId = $this->chats()->addPrivate($alice, $bob);
        $this->messageRepository()->add($chatId, $alice, 'old', [], DateTime::fromUnix(5));
        $this->setActor($alice);
        $chunks = $this->runSse($this->query([]), new FakeSseClock(100, 1));
        $frames = $this->syncFrames($chunks);
        self::assertCount(1, $frames);
        self::assertSame(100, $frames[0]['now']);
        self::assertSame(0, $frames[0]['afterId']);
        self::assertSame([], $frames[0]['chats']);
        self::assertSame([], $frames[0]['newChats']);
        self::assertSame([], (array) $frames[0]['messages']);
    }

    /**
     * since=0 догоняет send; кадр несёт afterId последней строки.
     *
     * @return void
     */
    public function testEpochCatchUpAfterSend(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $chatId = $this->chats()->addPrivate($alice, $bob);
        $sentAt = DateTime::fromUnix(50);
        $messageId = $this->messageRepository()->add($chatId, $alice, 'hi', [], $sentAt);
        $this->setActor($alice);
        $frames = $this->syncFrames($this->runSse(
            $this->query(['since' => '0']),
            new FakeSseClock(50, 1),
        ));
        self::assertCount(1, $frames);
        self::assertSame(50, $frames[0]['now']);
        self::assertSame($messageId, $frames[0]['afterId']);
        self::assertCount(1, $frames[0]['chats']);
        self::assertSame($chatId, $frames[0]['chats'][0]['id']);
        self::assertSame([], $frames[0]['newChats']);
        self::assertSame('hi', $frames[0]['messages'][$chatId][0]['content']);
        self::assertSame('alice', $frames[0]['messages'][$chatId][0]['username']);
    }

    /**
     * Два id в одну секунду: limit 1 не теряет второе по afterId.
     *
     * @return void
     */
    public function testKeysetDoesNotDropSameSecond(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $chatId = $this->chats()->addPrivate($alice, $bob);
        $sentAt = DateTime::fromUnix(70);
        $firstId = $this->messageRepository()->add($chatId, $alice, 'a', [], $sentAt);
        $secondId = $this->messageRepository()->add($chatId, $alice, 'b', [], $sentAt);
        $horizon = DateTime::fromUnix(80);
        $firstPage = $this->messageRepository()->getUpdatedSince([$chatId], $alice, 70, 0, $horizon, 1);
        self::assertCount(1, $firstPage);
        self::assertSame($firstId, $firstPage[0]->getId());
        $secondPage = $this->messageRepository()->getUpdatedSince(
            [$chatId],
            $alice,
            $firstPage[0]->getUpdatedAt()->toUnix(),
            $firstPage[0]->getId(),
            $horizon,
            1,
        );
        self::assertCount(1, $secondPage);
        self::assertSame($secondId, $secondPage[0]->getId());
        self::assertSame(70, $firstPage[0]->getUpdatedAt()->toUnix());
        self::assertNotSame(80, $firstPage[0]->getUpdatedAt()->toUnix());
    }

    /**
     * Reconnect с парой afterId пропускает уже отданное.
     *
     * @return void
     */
    public function testReconnectSkipsDeliveredId(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $chatId = $this->chats()->addPrivate($alice, $bob);
        $sentAt = DateTime::fromUnix(40);
        $firstId = $this->messageRepository()->add($chatId, $alice, 'a', [], $sentAt);
        $secondId = $this->messageRepository()->add($chatId, $alice, 'b', [], $sentAt);
        $this->setActor($alice);
        $frames = $this->syncFrames($this->runSse(
            $this->query(['since' => '40', 'afterId' => (string) $firstId]),
            new FakeSseClock(40, 1),
        ));
        self::assertCount(1, $frames);
        self::assertSame($secondId, $frames[0]['afterId']);
        self::assertCount(1, $frames[0]['messages'][$chatId]);
        self::assertSame('b', $frames[0]['messages'][$chatId][0]['content']);
    }

    /**
     * Reconnect без кадра не шлёт : ping сразу (lastWrite с открытия потока).
     *
     * @return void
     */
    public function testReconnectDoesNotPingImmediately(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $chunks = $this->runSse($this->query(['since' => '0']), new FakeSseClock(100, 1));
        self::assertStringNotContainsString(': ping', $chunks);
        self::assertSame([], $this->syncFrames($chunks));
    }

    /**
     * ISO в since — до потока.
     *
     * @return void
     */
    public function testInvalidSince(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice);
        try {
            $this->runSse($this->query(['since' => '2026-09-04T00:00:00Z']), new FakeSseClock(1, 0));
            self::fail('CHAT_INVALID expected');
        } catch (ChatInvalidException $exception) {
            self::assertSame('CHAT_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Пустой IN сообщений без запроса.
     *
     * @return void
     */
    public function testEmptyChatIdsSkipQuery(): void
    {
        self::assertSame(
            [],
            $this->messageRepository()->getUpdatedSince([], 1, 0, 0, DateTime::fromUnix(1), 10),
        );
    }

    /**
     * Цикл SSE в буфер.
     *
     * @param IHttpRequest $httpRequest Query.
     * @param FakeSseClock $sseClock Часы.
     *
     * @return string Байты.
     */
    private function runSse(IHttpRequest $httpRequest, FakeSseClock $sseClock): string
    {
        $chunks = '';
        $this->sseService($sseClock)->run(
            $httpRequest,
            new SseEmitter(
                static function (string $chunk) use (&$chunks): void {
                    $chunks .= $chunk;
                },
                false,
            ),
        );

        return $chunks;
    }

    /**
     * JSON кадров event: sync.
     *
     * @param string $chunks Байты.
     *
     * @return array<int, array<string, mixed>> Кадры.
     */
    private function syncFrames(string $chunks): array
    {
        preg_match_all('/event: sync\ndata: ([^\n]+)\n\n/', $chunks, $matches);
        $frames = [];
        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true);
            self::assertIsArray($decoded);
            $frames[] = $decoded;
        }

        return $frames;
    }

    /**
     * Сценарий с подменёнными часами.
     *
     * @param FakeSseClock $sseClock Часы.
     *
     * @return ChatSseService Сценарий.
     */
    private function sseService(FakeSseClock $sseClock): ChatSseService
    {
        $gateway = $this->smartTableGateway();

        return new ChatSseService(
            $this->userAccess(),
            $this->chats(),
            new ChatViewAssembler(
                new ChatRepository($gateway->open(ChatTable::class)->records()),
                new ChatMemberRepository($gateway->open(ChatMemberTable::class)->records()),
                $this->messageRepository(),
            ),
            $this->messageRepository(),
            $this->userAccounts(),
            $sseClock,
        );
    }

    /**
     * Репозиторий сообщений.
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
     * Снимок query.
     *
     * @param array<string, string> $query Параметры.
     *
     * @return IHttpRequest Снимок.
     */
    private function query(array $query): IHttpRequest
    {
        $httpRequest = $this->createStub(IHttpRequest::class);
        $httpRequest->method('getQueryValue')->willReturnCallback(
            static function (string $name) use ($query): mixed {
                return $query[$name] ?? null;
            },
        );

        return $httpRequest;
    }

    /**
     * boot и контейнеры.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectChat(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
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
        $userAccess = $userContainer->get(IUserAccess::class);
        self::assertInstanceOf(IUserAccess::class, $userAccess);
        $this->userAccess = $userAccess;
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
     * Ставит актора.
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
     * Фасад чатов.
     *
     * @return IChats Фасад.
     */
    private function chats(): IChats
    {
        self::assertInstanceOf(IChats::class, $this->chats);

        return $this->chats;
    }

    /**
     * Фасад учётки.
     *
     * @return IUserAccounts Фасад.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Guard.
     *
     * @return IUserAccess Guard.
     */
    private function userAccess(): IUserAccess
    {
        self::assertInstanceOf(IUserAccess::class, $this->userAccess);

        return $this->userAccess;
    }

    /**
     * Шлюз.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }
}
