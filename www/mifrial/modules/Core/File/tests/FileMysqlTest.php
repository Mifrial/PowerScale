<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests;

use Mifrial\Core\File\Dto\FilePageQuery;
use Mifrial\Core\File\Dto\NewFile;
use Mifrial\Core\File\Repository\FileRepository;
use Mifrial\Core\File\Schema\FileSchema;
use Mifrial\Core\File\Table\FileTable;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Core\User\Tests\UserMysqlTables;
use PHPUnit\Framework\TestCase;

final class FileMysqlTest extends TestCase
{
    private ?ISmartTableGateway $smartTableGateway = null;

    private ?IUserAccounts $userAccounts = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectPorts();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for File tests');
        }

        $this->dropTables();
        $this->installSchemas();
    }

    /**
     * Снос таблиц.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropTables();
    }

    /**
     * insert/find по public_id и id; unique держит public_id.
     *
     * @return void
     */
    public function testInsertFindAndUnique(): void
    {
        $ownerId = $this->addUser('alice');
        $repository = $this->repository();
        $publicId = str_repeat('a', 32);
        $fileId = $repository->add($this->newFile($ownerId, $publicId, 'image/png', 100));
        $byPublicId = $repository->findByPublicId($publicId);
        self::assertNotNull($byPublicId);
        self::assertSame($fileId, $byPublicId->getId());
        self::assertSame($ownerId, $byPublicId->getOwnerId());
        self::assertSame('cover.png', $byPublicId->getOriginalName());
        self::assertSame($fileId, $repository->getById($fileId)->getId());
        self::assertNull($repository->findByPublicId('not-a-public-id'));

        $this->expectException(UniqueConstraintException::class);
        $repository->add($this->newFile($ownerId, $publicId, 'image/png', 200));
    }

    /**
     * archive и фильтры страницы.
     *
     * @return void
     */
    public function testArchiveAndFindPage(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $repository = $this->repository();
        $first = $repository->add($this->newFile($alice, str_repeat('1', 32), 'image/png', 100, 100));
        $second = $repository->add($this->newFile($bob, str_repeat('2', 32), 'image/jpeg', 200, 200));
        $third = $repository->add($this->newFile($alice, str_repeat('3', 32), 'image/png', 300, 300));
        $repository->markArchived($second, DateTime::fromUnix(400));
        self::assertTrue($repository->getById($second)->isArchived());
        self::assertSame(400, $repository->getById($second)->getArchivedAt()?->toUnix());

        $page = $repository->findPage(new FilePageQuery(10, 0, null, null, null, null, null));
        self::assertSame(3, $page->getTotal());
        self::assertSame([$third, $second, $first], array_map(
            static fn ($fileRecord): int => $fileRecord->getId(),
            $page->getRecords(),
        ));

        $png = $repository->findPage(new FilePageQuery(10, 0, 'image/png', null, null, null, null));
        self::assertSame([$third, $first], array_column(
            array_map(static fn ($fileRecord): array => ['id' => $fileRecord->getId()], $png->getRecords()),
            'id',
        ));
        $owner = $repository->findPage(new FilePageQuery(10, 0, null, $alice, null, null, null));
        self::assertSame(2, $owner->getTotal());
        $archived = $repository->findPage(new FilePageQuery(10, 0, null, null, true, null, null));
        self::assertSame([$second], array_map(
            static fn ($fileRecord): int => $fileRecord->getId(),
            $archived->getRecords(),
        ));
        $active = $repository->findPage(new FilePageQuery(10, 0, null, null, false, null, null));
        self::assertSame([$third, $first], array_map(
            static fn ($fileRecord): int => $fileRecord->getId(),
            $active->getRecords(),
        ));
        $range = $repository->findPage(new FilePageQuery(10, 0, null, null, null, 200, 300));
        self::assertSame(2, $range->getTotal());
    }

    /**
     * Подключение MySQL.
     *
     * @return void
     */
    private function connectPorts(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->smartTableGateway = $smartTableGateway;
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            self::markTestSkipped('test connection is not Illuminate');
        }

        $databaseConnection->ping();
        $userAccounts = $application->getLocator()->get(IUserContainer::class)->get(IUserAccounts::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        $this->userAccounts = $userAccounts;
    }

    /**
     * User + file.
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
        (new FileSchema($gateway->open(FileTable::class)->schema()))->install();
    }

    /**
     * Снос.
     *
     * @return void
     */
    private function dropTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $this->deleteIfExists($this->smartTableGateway->open(FileTable::class));
        UserMysqlTables::drop($this->smartTableGateway);
    }

    /**
     * deleteTable если есть.
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
     * Поля новой строки.
     *
     * @param int $ownerId Владелец.
     * @param string $publicId Внешний id.
     * @param string $mime Тип.
     * @param int $size Байт.
     * @param int $createdAt Unix.
     *
     * @return NewFile Поля.
     */
    private function newFile(
        int $ownerId,
        string $publicId,
        string $mime,
        int $size,
        int $createdAt = 100,
    ): NewFile {
        return NewFile::fromNormalized([
            'public_id' => $publicId,
            'owner_id' => $ownerId,
            'original_name' => 'cover.png',
            'mime' => $mime,
            'size' => $size,
            'sha256' => hash('sha256', $publicId),
            'storage_path' => substr(hash('sha256', $publicId), 0, 2)
                . '/' . substr(hash('sha256', $publicId), -2) . '/' . $publicId,
            'width' => null,
            'height' => null,
            'is_archived' => false,
            'created_at' => DateTime::fromUnix($createdAt),
        ]);
    }

    /**
     * Учётка.
     *
     * @param string $login Логин.
     *
     * @return int Id.
     */
    private function addUser(string $login): int
    {
        $userAccounts = $this->userAccounts;
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);

        return $userAccounts->addFromInput(['login' => $login, 'name' => $login]);
    }

    /**
     * Репозиторий.
     *
     * @return FileRepository Репозиторий.
     */
    private function repository(): FileRepository
    {
        return new FileRepository(
            $this->smartTableGateway()->open(FileTable::class)->records(),
        );
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
