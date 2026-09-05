<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\GatewayHarness;
use Mifrial\Versioning\Space\Dto\ClusterSpec;
use Mifrial\Versioning\Space\Dto\CommitEntry;
use Mifrial\Versioning\Space\Dto\VersionRecord;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;
use Mifrial\Versioning\Space\Exception\SpaceNotFoundException;
use Mifrial\Versioning\Space\Interface\Service\IVersionedRepository;
use Mifrial\Versioning\Space\Service\VersionedCatalog;
use Mifrial\Versioning\Space\Tests\Fixture\NoteIdentityTable;
use Mifrial\Versioning\Space\Tests\Fixture\NoteRevisionItemTable;
use Mifrial\Versioning\Space\Tests\Fixture\NoteRevisionTable;
use Mifrial\Versioning\Space\Tests\Fixture\NoteSpaceTable;
use Mifrial\Versioning\Space\Tests\Fixture\NoteVersionTable;
use PHPUnit\Framework\TestCase;

final class SpaceMysqlTest extends TestCase
{
    private ?ISmartTableGateway $gateway = null;

    private ?IVersionedRepository $repository = null;

    /**
     * Подключается к MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectGateway();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Versioning tests');
        }

        $this->dropFixtureTables();
        $this->createFixtureTables();
        $this->repository = $this->makeRepository();
    }

    /**
     * Снимает фикстурные таблицы.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropFixtureTables();
    }

    /**
     * Два пространства не делят ревизии.
     *
     * @return void
     */
    public function testSpacesAreIsolated(): void
    {
        $repository = $this->repository();
        $spaceA = $repository->addSpace('A');
        $spaceB = $repository->addSpace('B');
        $recordA = $repository->commit($spaceA, [$this->noteCreate('alpha')]);
        $repository->commit($spaceB, [$this->noteCreate('beta')]);
        self::assertSame(1, $recordA->getRevision());
        self::assertSame('alpha', $this->noteTitle($repository->getRevision($spaceA, 1)->getItems()[0]));
        self::assertSame('beta', $this->noteTitle($repository->getRevision($spaceB, 1)->getItems()[0]));
    }

    /**
     * Change даёт новый version, старый срез не меняется.
     *
     * @return void
     */
    public function testChangeKeepsOldSlice(): void
    {
        $repository = $this->repository();
        $spaceId = $repository->addSpace('World');
        $first = $repository->commit($spaceId, [$this->noteCreate('old', 'a')]);
        $entityId = $repository->getRevision($spaceId, 1)->getItems()[0]->getEntityId();
        $repository->commit($spaceId, [$this->noteChange($entityId, 'new', 'b')]);
        $sliceOne = $repository->getRevision($spaceId, 1);
        $sliceTwo = $repository->getRevision($spaceId, 2);
        self::assertSame(1, $first->getRevision());
        self::assertSame('old', $this->noteTitle($sliceOne->getItems()[0]));
        self::assertSame('new', $this->noteTitle($sliceTwo->getItems()[0]));
        self::assertSame($entityId, $sliceTwo->getItems()[0]->getEntityId());
        self::assertNotSame($sliceOne->getItems()[0]->getVersionId(), $sliceTwo->getItems()[0]->getVersionId());
    }

    /**
     * Keep всего состава без изменения → SPACE_INVALID.
     *
     * @return void
     */
    public function testKeepAllIsInvalid(): void
    {
        $repository = $this->repository();
        $spaceId = $repository->addSpace('World');
        $versionId = $repository->commit($spaceId, [$this->noteCreate('one')]);
        $item = $repository->getRevision($spaceId, $versionId->getRevision())->getItems()[0];
        try {
            $repository->commit($spaceId, [CommitEntry::keep($item->getVersionId())]);
            self::fail('unchanged composition must fail');
        } catch (SpaceInvalidException $exception) {
            self::assertSame('SPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Флаг: тот же состав даёт новую ревизию и те же version_id.
     *
     * @return void
     */
    public function testAllowUnchangedComposition(): void
    {
        $repository = $this->repository();
        $spaceId = $repository->addSpace('World');
        $first = $repository->commit($spaceId, [$this->noteCreate('one')]);
        $item = $repository->getRevision($spaceId, $first->getRevision())->getItems()[0];
        $second = $repository->commit(
            $spaceId,
            [CommitEntry::keep($item->getVersionId())],
            true,
        );
        self::assertSame(2, $second->getRevision());
        self::assertSame(
            $item->getVersionId(),
            $repository->getRevision($spaceId, 2)->getItems()[0]->getVersionId(),
        );
    }

    /**
     * Keep плюс change — вторая ревизия.
     *
     * @return void
     */
    public function testKeepPlusChange(): void
    {
        $repository = $this->repository();
        $spaceId = $repository->addSpace('World');
        $repository->commit($spaceId, [
            $this->noteCreate('keep-me'),
            $this->noteCreate('change-me'),
        ]);
        $items = $repository->getRevision($spaceId, 1)->getItems();
        $repository->commit($spaceId, [
            CommitEntry::keep($items[0]->getVersionId()),
            $this->noteChange($items[1]->getEntityId(), 'changed'),
        ]);
        $sliceTwo = $repository->getRevision($spaceId, 2)->getItems();
        self::assertSame($items[0]->getVersionId(), $sliceTwo[0]->getVersionId());
        self::assertSame('changed', $this->noteTitle($sliceTwo[1]));
        self::assertSame($items[1]->getEntityId(), $sliceTwo[1]->getEntityId());
    }

    /**
     * Tombstone во второй ревизии не попадает в первую.
     *
     * @return void
     */
    public function testTombstoneOnlyInNewSlice(): void
    {
        $repository = $this->repository();
        $spaceId = $repository->addSpace('World');
        $repository->commit($spaceId, [$this->noteCreate('live')]);
        $entityId = $repository->getRevision($spaceId, 1)->getItems()[0]->getEntityId();
        $repository->commit($spaceId, [$this->noteChange($entityId, 'dead', '', false)]);
        self::assertTrue($repository->getRevision($spaceId, 1)->getItems()[0]->isActive());
        $tombstone = $repository->getRevision($spaceId, 2)->getItems()[0];
        self::assertFalse($tombstone->isActive());
        self::assertNotSame(
            $repository->getRevision($spaceId, 1)->getItems()[0]->getVersionId(),
            $tombstone->getVersionId(),
        );
    }

    /**
     * Нет ревизии 9 → SPACE_NOT_FOUND.
     *
     * @return void
     */
    public function testMissingRevision(): void
    {
        $repository = $this->repository();
        $spaceId = $repository->addSpace('World');
        $repository->commit($spaceId, [$this->noteCreate('one')]);
        try {
            $repository->getRevision($spaceId, 9);
            self::fail('missing revision must fail');
        } catch (SpaceNotFoundException $exception) {
            self::assertSame('SPACE_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Пустой title пространства и пустой commit.
     *
     * @return void
     */
    public function testEmptyTitleAndCommit(): void
    {
        $repository = $this->repository();
        try {
            $repository->addSpace('  ');
            self::fail('empty space title must fail');
        } catch (SpaceInvalidException $exception) {
            self::assertSame('SPACE_INVALID', $exception->getErrorCode());
        }

        $spaceId = $repository->addSpace('World');
        try {
            $repository->commit($spaceId, []);
            self::fail('empty commit must fail');
        } catch (SpaceInvalidException $exception) {
            self::assertSame('SPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет ревизий — null; после commit — последний номер.
     *
     * @return void
     */
    public function testFindLatestRevision(): void
    {
        $repository = $this->repository();
        $spaceId = $repository->addSpace('World');
        self::assertNull($repository->findLatestRevision($spaceId));
        $repository->commit($spaceId, [$this->noteCreate('a')]);
        $repository->commit($spaceId, [$this->noteChange(
            $repository->getRevision($spaceId, 1)->getItems()[0]->getEntityId(),
            'b',
        )]);
        $latest = $repository->findLatestRevision($spaceId);
        self::assertNotNull($latest);
        self::assertSame(2, $latest->getRevision());
        try {
            $repository->findLatestRevision(999);
            self::fail('missing space must fail');
        } catch (SpaceNotFoundException $exception) {
            self::assertSame('SPACE_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * updateSpace и лента ревизий с itemCount.
     *
     * @return void
     */
    public function testUpdateSpaceAndRevisionList(): void
    {
        $repository = $this->repository();
        $spaceId = $repository->addSpace('World');
        self::assertSame([], $repository->getRevisionList($spaceId));
        $repository->updateSpace($spaceId, '  Renamed  ');
        self::assertSame('Renamed', $repository->getSpace($spaceId)->getTitle());
        $repository->commit($spaceId, [
            $this->noteCreate('a'),
            $this->noteCreate('b'),
        ]);
        $entityId = $repository->getRevision($spaceId, 1)->getItems()[0]->getEntityId();
        $repository->commit($spaceId, [$this->noteChange($entityId, 'c')]);
        $summaries = $repository->getRevisionList($spaceId);
        self::assertCount(2, $summaries);
        self::assertSame(2, $summaries[0]->getRevision());
        self::assertSame(1, $summaries[0]->getItemCount());
        self::assertSame(1, $summaries[1]->getRevision());
        self::assertSame(2, $summaries[1]->getItemCount());
        try {
            $repository->updateSpace($spaceId, '  ');
            self::fail('empty title must fail');
        } catch (SpaceInvalidException $exception) {
            self::assertSame('SPACE_INVALID', $exception->getErrorCode());
        }

        try {
            $repository->getRevisionList(999);
            self::fail('missing space must fail');
        } catch (SpaceNotFoundException $exception) {
            self::assertSame('SPACE_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Номер ревизии — MAX+1, не вход клиента.
     *
     * @return void
     */
    public function testRevisionNumbersAreSequential(): void
    {
        $repository = $this->repository();
        $spaceId = $repository->addSpace('World');
        $first = $repository->commit($spaceId, [$this->noteCreate('a')]);
        $entityId = $repository->getRevision($spaceId, 1)->getItems()[0]->getEntityId();
        $second = $repository->commit($spaceId, [$this->noteChange($entityId, 'b')]);
        self::assertSame(1, $first->getRevision());
        self::assertSame(2, $second->getRevision());
        self::assertSame($spaceId, $second->getSpaceId());
    }

    /**
     * Повтор getRevision без записи даёт тот же title.
     *
     * @return void
     */
    public function testGetRevisionRepeat(): void
    {
        $repository = $this->repository();
        $spaceId = $repository->addSpace('World');
        $repository->commit($spaceId, [$this->noteCreate('cached')]);
        $first = $this->noteTitle($repository->getRevision($spaceId, 1)->getItems()[0]);
        $second = $this->noteTitle($repository->getRevision($spaceId, 1)->getItems()[0]);
        self::assertSame('cached', $first);
        self::assertSame($first, $second);
    }

    /**
     * commit часов внутри внешней TX.
     *
     * @return void
     */
    public function testCommitJoinsOuterTransaction(): void
    {
        $repository = $this->repository();
        $spaceId = 0;
        $this->gateway()->transaction(function () use ($repository, &$spaceId): void {
            $spaceId = $repository->addSpace('outer');
            $repository->commit($spaceId, [$this->noteCreate('in-tx')]);
        });
        self::assertSame('in-tx', $this->noteTitle($repository->getRevision($spaceId, 1)->getItems()[0]));
    }

    /**
     * Откат внешней TX не оставляет срез в кэше.
     *
     * @return void
     */
    public function testRolledOuterTransactionDoesNotCacheSlice(): void
    {
        $repository = $this->repository();
        $spaceId = 0;
        try {
            $this->gateway()->transaction(function () use ($repository, &$spaceId): void {
                $spaceId = $repository->addSpace('outer');
                $repository->commit($spaceId, [$this->noteCreate('gone')]);
                throw new SpaceInvalidException('rollback after commit');
            });
            self::fail('outer transaction must rethrow');
        } catch (SpaceInvalidException $exception) {
            self::assertSame('SPACE_INVALID', $exception->getErrorCode());
        }

        try {
            $repository->getRevision($spaceId, 1);
            self::fail('rolled commit must not be visible');
        } catch (SpaceNotFoundException $exception) {
            self::assertSame('SPACE_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Репозиторий после setUp.
     *
     * @return IVersionedRepository Фасад.
     */
    private function repository(): IVersionedRepository
    {
        self::assertInstanceOf(IVersionedRepository::class, $this->repository);

        return $this->repository;
    }

    /**
     * Собирает каталог фикстуры.
     *
     * @return IVersionedRepository Репозиторий.
     */
    private function makeRepository(): IVersionedRepository
    {
        $cachePath = sys_get_temp_dir() . '/mifrial-vs-mysql-' . uniqid('', true);
        $catalog = new VersionedCatalog(
            $this->gateway(),
            GatewayHarness::fileStore($cachePath),
            true,
            [],
        );

        return $catalog->openCluster(new ClusterSpec(
            NoteIdentityTable::class,
            NoteVersionTable::class,
            NoteSpaceTable::class,
            NoteRevisionTable::class,
            NoteRevisionItemTable::class,
        ));
    }

    /**
     * Create фикстуры notes.
     *
     * @param string $title Заголовок.
     * @param string $body Текст.
     * @param bool $active Marker.
     *
     * @return CommitEntry Пункт.
     */
    private function noteCreate(string $title, string $body = '', bool $active = true): CommitEntry
    {
        return CommitEntry::create([], ['title' => $title, 'body' => $body], $active);
    }

    /**
     * Change фикстуры notes.
     *
     * @param int $entityId Identity.
     * @param string $title Заголовок.
     * @param string $body Текст.
     * @param bool $active Marker.
     *
     * @return CommitEntry Пункт.
     */
    private function noteChange(int $entityId, string $title, string $body = '', bool $active = true): CommitEntry
    {
        return CommitEntry::change($entityId, ['title' => $title, 'body' => $body], $active);
    }

    /**
     * Title из полей фикстуры.
     *
     * @param VersionRecord $versionRecord Экземпляр.
     *
     * @return string Title.
     */
    private function noteTitle(VersionRecord $versionRecord): string
    {
        $title = $versionRecord->getFields()['title'] ?? null;
        self::assertIsString($title);

        return $title;
    }

    /**
     * Создаёт пять карт.
     *
     * @return void
     */
    private function createFixtureTables(): void
    {
        $this->gateway()->open(NoteIdentityTable::class)->schema()->createTable();
        $this->gateway()->open(NoteSpaceTable::class)->schema()->createTable();
        $this->gateway()->open(NoteVersionTable::class)->schema()->createTable();
        $this->gateway()->open(NoteRevisionTable::class)->schema()->createTable();
        $this->gateway()->open(NoteRevisionItemTable::class)->schema()->createTable();
    }

    /**
     * Снимает карты в порядке FK.
     *
     * @return void
     */
    private function dropFixtureTables(): void
    {
        if (!$this->gateway instanceof ISmartTableGateway) {
            return;
        }

        $this->deleteIfExists($this->gateway()->open(NoteRevisionItemTable::class));
        $this->deleteIfExists($this->gateway()->open(NoteRevisionTable::class));
        $this->deleteIfExists($this->gateway()->open(NoteVersionTable::class));
        $this->deleteIfExists($this->gateway()->open(NoteSpaceTable::class));
        $this->deleteIfExists($this->gateway()->open(NoteIdentityTable::class));
    }

    /**
     * deleteTable если физика есть.
     *
     * @param IOpenedTable $openedTable Карта.
     *
     * @return void
     */
    private function deleteIfExists(IOpenedTable $openedTable): void
    {
        if ($openedTable->schema()->exists()) {
            $openedTable->schema()->deleteTable();
        }
    }

    /**
     * Boot шлюза и ping.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectGateway(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $connection = $application->getLocator()->get(ISmartTableContainer::class)->get(IDatabaseConnection::class);
        if (!$connection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $connection->ping();
        $this->gateway = GatewayHarness::make($connection);
    }

    /**
     * Шлюз после setUp.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function gateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->gateway);

        return $this->gateway;
    }
}
