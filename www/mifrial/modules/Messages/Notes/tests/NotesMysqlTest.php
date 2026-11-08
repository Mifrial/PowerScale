<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
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
use Mifrial\Messages\Notes\Dto\NewNote;
use Mifrial\Messages\Notes\Dto\NoteFilter;
use Mifrial\Messages\Notes\Dto\NoteHostFilter;
use Mifrial\Messages\Notes\Dto\NotePatch;
use Mifrial\Messages\Notes\Exception\NoteInvalidException;
use Mifrial\Messages\Notes\Exception\NoteNotFoundException;
use Mifrial\Messages\Notes\Interface\Container\INotesContainer;
use Mifrial\Messages\Notes\Interface\Service\INotes;
use Mifrial\Messages\Notes\Schema\NotesSchema;
use Mifrial\Messages\Notes\Table\NoteTable;
use PHPUnit\Framework\TestCase;

final class NotesMysqlTest extends TestCase
{
    use MysqlSchemaFixture;

    private ?INotes $notes = null;

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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Notes tests');
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
     * Снимает `note`: её FK на `user` мешает соседним наборам
     * пересоздавать таблицу пользователя через UserMysqlTables::drop.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $gateway = $this->smartTableGateway;
        if (!$gateway instanceof ISmartTableGateway) {
            return;
        }

        $noteSchema = $gateway->open(NoteTable::class)->schema();
        if ($noteSchema->exists()) {
            $noteSchema->deleteTable();
        }
    }

    /**
     * CRUD одной заметки и производный body_text.
     *
     * @return void
     */
    public function testAddUpdateDelete(): void
    {
        $owner = $this->addUser('notes-owner');
        $actor = new RequestActor($owner, [], false);
        $record = $this->notes()->add($actor, NewNote::fromNormalized([
            'hostType' => 'character',
            'hostId' => 1,
            'title' => 'Первый',
            'bodyHtml' => '<p>Текст <strong>заметки</strong></p>',
        ]));

        self::assertSame('Первый', $record->getTitle());
        self::assertSame('character', $record->getHostType());
        self::assertSame(1, $record->getHostId());
        self::assertSame($owner, $record->getAuthorId());
        self::assertSame('Текст заметки', $record->getBodyText());
        self::assertSame(0, $record->getSortOrder());
        self::assertTrue($record->isActive());

        $updated = $this->notes()->update($actor, $record->getId(), NotePatch::fromNormalized([
            'title' => 'Второй',
            'bodyHtml' => '<p>Новый <em>текст</em></p>',
        ]));
        self::assertSame('Второй', $updated->getTitle());
        self::assertSame('Новый текст', $updated->getBodyText());

        $this->notes()->delete($actor, $record->getId());
        $this->expectException(NoteNotFoundException::class);
        $this->notes()->get($actor, $record->getId());
    }

    /**
     * Несколько заметок на одну сущность и ручной порядок.
     *
     * @return void
     */
    public function testSeveralNotesAndReorder(): void
    {
        $owner = $this->addUser('notes-reorder');
        $actor = new RequestActor($owner, [], false);
        $first = $this->addNote($actor, 'character', 1, 'Первый');
        $second = $this->addNote($actor, 'character', 1, 'Второй');
        $third = $this->addNote($actor, 'character', 1, 'Третий');

        $page = $this->notes()->findPage($actor, NoteFilter::fromNormalized([
            'hostType' => 'character',
            'hostId' => 1,
            'state' => 'active',
        ]));
        self::assertSame(3, $page->getTotal());
        self::assertSame([$first->getId(), $second->getId(), $third->getId()], $this->idsOf($page->getItems()));

        $this->notes()->reorder($actor, 'character', 1, [$third->getId(), $first->getId(), $second->getId()]);
        $reordered = $this->notes()->findPage($actor, NoteFilter::fromNormalized([
            'hostType' => 'character',
            'hostId' => 1,
        ]));
        self::assertSame([$third->getId(), $first->getId(), $second->getId()], $this->idsOf($reordered->getItems()));

        $this->expectException(NoteInvalidException::class);
        $this->notes()->reorder($actor, 'character', 1, [$third->getId(), $first->getId()]);
    }

    /**
     * Обычный пользователь не видит чужое; bypass читает, но не пишет.
     *
     * @return void
     */
    public function testOwnershipAndBypass(): void
    {
        $alice = $this->addUser('notes-alice');
        $bob = $this->addUser('notes-bob');
        $admin = $this->addUser('notes-admin');
        $aliceActor = new RequestActor($alice, [], false);
        $bobActor = new RequestActor($bob, [], false);
        $adminActor = new RequestActor($admin, [], true);
        $record = $this->addNote($aliceActor, 'character', 1, 'Личное');

        $bobPage = $this->notes()->findPage($bobActor, NoteFilter::fromNormalized(['hostType' => 'character']));
        self::assertSame(0, $bobPage->getTotal());

        try {
            $this->notes()->get($bobActor, $record->getId());
            self::fail('foreign get must fail');
        } catch (NoteNotFoundException $exception) {
            self::assertSame('NOTE_NOT_FOUND', $exception->getErrorCode());
        }

        self::assertSame($record->getId(), $this->notes()->get($adminActor, $record->getId())->getId());

        foreach ([
            fn () => $this->notes()->update($adminActor, $record->getId(), NotePatch::fromNormalized(['title' => 'x'])),
            fn () => $this->notes()->delete($adminActor, $record->getId()),
        ] as $writeAttempt) {
            try {
                $writeAttempt();
                self::fail('bypass write must fail');
            } catch (ActionException $exception) {
                self::assertSame('AUTH_DENIED', $exception->getErrorCode());
            }
        }
    }

    /**
     * Поиск, state и фильтр автора.
     *
     * @return void
     */
    public function testFindPageFilters(): void
    {
        $owner = $this->addUser('notes-filter');
        $actor = new RequestActor($owner, [], false);
        $alpha = $this->addNote($actor, 'character', 1, 'Альфа', '<p>северный тракт</p>');
        $beta = $this->addNote($actor, 'game', 2, 'Бета', '<p>южный тракт</p>');
        $this->notes()->pin($actor, $beta->getId(), true);

        $byTitle = $this->notes()->findPage($actor, NoteFilter::fromNormalized([
            'q' => 'альфа',
            'state' => 'all',
        ]));
        self::assertSame([$alpha->getId()], $this->idsOf($byTitle->getItems()));

        $byBody = $this->notes()->findPage($actor, NoteFilter::fromNormalized([
            'q' => 'южный',
            'state' => 'all',
        ]));
        self::assertSame([$beta->getId()], $this->idsOf($byBody->getItems()));

        $pinned = $this->notes()->findPage($actor, NoteFilter::fromNormalized([
            'pinned' => true,
            'state' => 'all',
        ]));
        self::assertSame([$beta->getId()], $this->idsOf($pinned->getItems()));

        $this->notes()->deactivateForHost('game', 2);
        $archived = $this->notes()->findPage($actor, NoteFilter::fromNormalized(['state' => 'archived']));
        self::assertSame([$beta->getId()], $this->idsOf($archived->getItems()));
        $active = $this->notes()->findPage($actor, NoteFilter::fromNormalized(['state' => 'active']));
        self::assertSame([$alpha->getId()], $this->idsOf($active->getItems()));
    }

    /**
     * findHosts группирует по паре и считает заметки.
     *
     * @return void
     */
    public function testFindHosts(): void
    {
        $owner = $this->addUser('notes-hosts');
        $actor = new RequestActor($owner, [], false);
        $this->addNote($actor, 'character', 1, 'A');
        $this->addNote($actor, 'character', 1, 'B');
        $this->addNote($actor, 'game', 7, 'C');

        $groups = $this->notes()->findHosts($actor, NoteHostFilter::fromNormalized(['state' => 'active']));
        self::assertCount(2, $groups);
        $byKey = [];
        foreach ($groups as $group) {
            $byKey[$group->getHostType() . ':' . $group->getHostId()] = $group->getCount();
        }
        self::assertSame(2, $byKey['character:1']);
        self::assertSame(1, $byKey['game:7']);
    }

    /**
     * deactivateForHost гасит только свою пару.
     *
     * @return void
     */
    public function testDeactivateForHost(): void
    {
        $owner = $this->addUser('notes-deactivate');
        $actor = new RequestActor($owner, [], false);
        $this->addNote($actor, 'character', 1, 'A');
        $this->addNote($actor, 'character', 1, 'B');
        $this->addNote($actor, 'character', 2, 'C');

        self::assertSame(2, $this->notes()->deactivateForHost('character', 1));
        self::assertSame(0, $this->notes()->deactivateForHost('character', 1));

        $page = $this->notes()->findPage($actor, NoteFilter::fromNormalized([
            'hostType' => 'character',
            'hostId' => 2,
        ]));
        self::assertSame(1, $page->getTotal());
        self::assertTrue($page->getItems()[0]->isActive());
    }

    /**
     * Подключает приложение и порты Notes.
     *
     * @return void
     */
    private function connectNotes(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $locator = $application->getLocator();
        $notesContainer = $locator->get(INotesContainer::class);
        self::assertInstanceOf(INotesContainer::class, $notesContainer);
        $notes = $notesContainer->get(INotes::class);
        self::assertInstanceOf(INotes::class, $notes);
        $this->notes = $notes;
        $userContainer = $locator->get(IUserContainer::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        $this->userAccounts = $userAccounts;
        $smartTableContainer = $locator->get(ISmartTableContainer::class);
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
     * Заметка с одним абзацем.
     *
     * @param RequestActor $actor Автор.
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     * @param string $title Заголовок.
     * @param string $bodyHtml HTML.
     *
     * @return \Mifrial\Messages\Notes\Dto\NoteRecord Запись.
     */
    private function addNote(
        RequestActor $actor,
        string $hostType,
        int $hostId,
        string $title,
        string $bodyHtml = '<p>текст</p>',
    ): \Mifrial\Messages\Notes\Dto\NoteRecord {
        return $this->notes()->add($actor, NewNote::fromNormalized([
            'hostType' => $hostType,
            'hostId' => $hostId,
            'title' => $title,
            'bodyHtml' => $bodyHtml,
        ]));
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
     * Id записей страницы.
     *
     * @param array<int, \Mifrial\Messages\Notes\Dto\NoteRecord> $records Записи.
     *
     * @return array<int, int> Id.
     */
    private function idsOf(array $records): array
    {
        return array_map(static fn (\Mifrial\Messages\Notes\Dto\NoteRecord $record): int => $record->getId(), $records);
    }

    /**
     * Фасад заметок после setUp.
     *
     * @return INotes Фасад.
     */
    private function notes(): INotes
    {
        self::assertInstanceOf(INotes::class, $this->notes);

        return $this->notes;
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
