<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Tests;

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
use Mifrial\Core\SmartTable\Tests\Support\MysqlSchemaFixture;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserAvatarTable;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Messages\Notes\Interface\Container\INotesContainer;
use Mifrial\Messages\Notes\Interface\Service\INotes;
use Mifrial\Messages\Notes\Schema\NotesSchema;
use Mifrial\Messages\Notes\Table\NoteTable;
use PHPUnit\Framework\TestCase;

final class NotesHttpMysqlTest extends TestCase
{
    use MysqlSchemaFixture;

    private ?IApplication $application = null;

    private ?IRequestContext $requestContext = null;

    private ?IUserAccounts $userAccounts = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    private ?IlluminateDatabaseConnection $databaseConnection = null;

    /**
     * Подключается к MySQL или skip; готовит схемы.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectNotes();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Notes HTTP tests');
        }

        $databaseConnection = $this->databaseConnection;
        self::assertInstanceOf(IlluminateDatabaseConnection::class, $databaseConnection);
        $this->prepareMysql(
            $databaseConnection,
            'notes',
            ['user', 'note'],
            function (): void {
                $this->installSchemas();
            },
        );
    }

    /**
     * Без актора — AUTH_REQUIRED.
     *
     * @return void
     */
    public function testAuthRequired(): void
    {
        $this->assertError('AUTH_REQUIRED', $this->dispatch('note.create', [
            'hostType' => 'character',
            'hostId' => 1,
            'title' => 'x',
        ]));
        $this->assertError('AUTH_REQUIRED', $this->dispatch('note.findPage', []));
    }

    /**
     * Полный HTTP-цикл заметки и права.
     *
     * @return void
     */
    public function testHttpCycleAndRights(): void
    {
        $alice = $this->addUser('notes-http-alice');
        $bob = $this->addUser('notes-http-bob');
        $this->setActor($alice);

        $created = $this->dispatch('note.create', [
            'hostType' => 'character',
            'hostId' => 3,
            'title' => '  Гаррик  ',
            'bodyHtml' => '<p>север</p>',
        ]);
        self::assertTrue($created['success']);
        self::assertSame('Гаррик', $created['data']['title']);
        self::assertSame('север', $created['data']['bodyText']);
        self::assertTrue($created['data']['canEdit']);
        self::assertTrue($created['data']['canDelete']);
        self::assertIsInt($created['data']['createdAt']);
        $noteId = $created['data']['id'];

        $page = $this->dispatch('note.findPage', ['hostType' => 'character', 'hostId' => 3]);
        self::assertTrue($page['success']);
        self::assertSame(1, $page['data']['total']);
        self::assertSame($noteId, $page['data']['items'][0]['id']);

        $updated = $this->dispatch('note.update', ['noteId' => $noteId, 'title' => 'Новый']);
        self::assertSame('Новый', $updated['data']['title']);

        $pinned = $this->dispatch('note.pin', ['noteId' => $noteId, 'pinned' => true]);
        self::assertTrue($pinned['data']['pinned']);

        $this->setActor($bob);
        $this->assertError('NOTE_NOT_FOUND', $this->dispatch('note.get', ['noteId' => $noteId]));
        $this->assertError('NOTE_NOT_FOUND', $this->dispatch('note.update', ['noteId' => $noteId, 'title' => 'x']));

        $this->setActor($bob, true);
        $foreign = $this->dispatch('note.get', ['noteId' => $noteId]);
        self::assertTrue($foreign['success']);
        self::assertFalse($foreign['data']['canEdit']);
        self::assertFalse($foreign['data']['canDelete']);
        $this->assertError('AUTH_DENIED', $this->dispatch('note.update', [
            'noteId' => $noteId,
            'title' => 'x',
        ]));

        $this->setActor($alice);
        $deleted = $this->dispatch('note.delete', ['noteId' => $noteId]);
        self::assertTrue($deleted['success']);
        self::assertTrue($deleted['data']['deleted']);
    }

    /**
     * findHosts и reorder через HTTP.
     *
     * @return void
     */
    public function testHostsAndReorder(): void
    {
        $owner = $this->addUser('notes-http-hosts');
        $this->setActor($owner);
        $first = $this->dispatch('note.create', [
            'hostType' => 'character',
            'hostId' => 1,
            'title' => 'A',
        ])['data']['id'];
        $second = $this->dispatch('note.create', [
            'hostType' => 'character',
            'hostId' => 1,
            'title' => 'B',
        ])['data']['id'];
        $this->dispatch('note.create', ['hostType' => 'game', 'hostId' => 2, 'title' => 'C']);

        $hosts = $this->dispatch('note.findHosts', ['state' => 'active']);
        self::assertTrue($hosts['success']);
        self::assertCount(2, $hosts['data']['items']);
        $byKey = [];
        foreach ($hosts['data']['items'] as $group) {
            $byKey[$group['hostType'] . ':' . $group['hostId']] = $group['count'];
        }
        self::assertSame(2, $byKey['character:1']);
        self::assertSame(1, $byKey['game:2']);

        $reordered = $this->dispatch('note.reorder', [
            'hostType' => 'character',
            'hostId' => 1,
            'noteIds' => [$second, $first],
        ]);
        self::assertTrue($reordered['success']);
        self::assertSame(2, $reordered['data']['reordered']);

        $this->assertError('NOTE_INVALID', $this->dispatch('note.reorder', [
            'hostType' => 'character',
            'hostId' => 1,
            'noteIds' => [$first],
        ]));
        $this->assertError('INVALID_PARAMS', $this->dispatch('note.findPage', ['unknown' => 1]));
    }

    /**
     * Подключает приложение и контекст.
     *
     * @return void
     */
    private function connectNotes(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $this->application = $application;
        $kernelContainer = $application->getLocator()->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;
        $notesContainer = $application->getLocator()->get(INotesContainer::class);
        self::assertInstanceOf(INotesContainer::class, $notesContainer);
        self::assertInstanceOf(INotes::class, $notesContainer->get(INotes::class));
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
        $this->databaseConnection = $databaseConnection;
    }

    /**
     * Ставит User, затем Notes.
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
            $gateway->open(UserAvatarTable::class)->schema(),
        ))->install();
        (new NotesSchema($gateway->open(NoteTable::class)->schema()))->install();
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
     * Актор запроса.
     *
     * @param int $userId Учётка.
     * @param bool $bypass Обход ACL.
     *
     * @return void
     */
    private function setActor(int $userId, bool $bypass = false): void
    {
        $this->requestContext()->setActor(new RequestActor($userId, [], $bypass));
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
