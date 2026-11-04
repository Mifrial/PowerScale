<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests;

use Mifrial\Core\File\Dto\FileSettings;
use Mifrial\Core\File\Dto\IncomingUpload;
use Mifrial\Core\File\Exception\FileDeniedException;
use Mifrial\Core\File\Exception\FileMimeInvalidException;
use Mifrial\Core\File\Exception\FileTooLargeException;
use Mifrial\Core\File\Interface\Container\IFileContainer;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Core\File\Repository\FileRepository;
use Mifrial\Core\File\Schema\FileSchema;
use Mifrial\Core\File\Service\FileByteService;
use Mifrial\Core\File\Service\FileIntake;
use Mifrial\Core\File\Service\FileReadService;
use Mifrial\Core\File\Service\FileStorageFactory;
use Mifrial\Core\File\Service\FileUploadService;
use Mifrial\Core\File\Service\FileViewAssembler;
use Mifrial\Core\File\Service\FilesService;
use Mifrial\Core\File\Table\FileTable;
use Mifrial\Core\File\Tests\Fixture\ByteCapture;
use Mifrial\Core\File\Tests\Fixture\FakeFileStorage;
use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserAvatarTable;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Core\User\Tests\UserMysqlTables;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FileHttpMysqlTest extends TestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private ?IApplication $application = null;

    private ?IRequestContext $requestContext = null;

    private ?IUserAccounts $userAccounts = null;

    private ?IUserAccess $userAccess = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    private string $storageRoot = '';

    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for File HTTP tests');
        }

        $this->storageRoot = sys_get_temp_dir() . '/opencode-file-http-' . bin2hex(random_bytes(6));
        $this->dropTables();
        $this->installSchemas();
    }

    /**
     * Снос таблиц и временного диска.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropTables();
        $this->removeDirectory($this->storageRoot);
        foreach ($this->temporaryFiles as $temporaryFile) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        $this->temporaryFiles = [];
    }

    /**
     * Гость AUTH_REQUIRED; без ключа DENIED; ключ проходит.
     *
     * @return void
     */
    public function testAdminActionsRequireFileView(): void
    {
        self::assertArrayHasKey('file.findPage', $this->application()->getModuleManager()->getRoutes());
        self::assertArrayHasKey('file.get', $this->application()->getModuleManager()->getRoutes());
        $anon = $this->dispatch('file.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertSame('AUTH_REQUIRED', $anon['error']['code']);
        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $denied = $this->dispatch('file.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertSame('AUTH_DENIED', $denied['error']['code']);
        $this->setActor($alice, ['file.view']);
        $ok = $this->dispatch('file.findPage', ['limit' => 10, 'offset' => 0]);
        self::assertTrue($ok['success']);
        self::assertSame(0, $ok['data']['total']);
        $missing = $this->dispatch('file.get', ['id' => 999]);
        self::assertSame('FILE_NOT_FOUND', $missing['error']['code']);
    }

    /**
     * Upload под актором: строка, байты, размеры; чужой тип отбит.
     *
     * @return void
     */
    public function testUploadAndReadPolicies(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $this->setActor($alice);
        $service = $this->uploadService(['path' => $this->storageRoot, 'max_size' => 1000, 'mime' => ['image/png']]);
        $view = $service->upload($this->incoming(base64_decode(self::PNG_BASE64, true), 'cover.png'));
        self::assertSame($alice, $view['ownerId']);
        self::assertSame('image/png', $view['mime']);
        self::assertSame(1, $view['width']);
        self::assertSame(1, $view['height']);
        self::assertSame(32, strlen((string) $view['publicId']));
        self::assertArrayNotHasKey('storagePath', $view);

        $this->setActor($alice, ['file.view']);
        $got = $this->dispatch('file.get', ['id' => $view['id']]);
        self::assertTrue($got['success']);
        self::assertSame($view['publicId'], $got['data']['publicId']);
        self::assertArrayNotHasKey('storagePath', $got['data']);
        $this->setActor($alice);

        $files = $this->files();
        $record = $files->requireReadable((string) $view['publicId']);
        self::assertSame($alice, $record->getOwnerId());
        self::assertSame($view['sha256'], $record->getSha256());
        self::assertFileExists($this->storageRoot . '/' . $record->getStoragePath());
        self::assertSame('cover.png', $record->getOriginalName());

        $this->setActor($bob);
        self::assertSame($view['publicId'], $files->requireReadable((string) $view['publicId'])->getPublicId());
        try {
            $files->requireOwned((string) $view['publicId']);
            self::fail('foreign file must be denied');
        } catch (FileDeniedException $exception) {
            self::assertSame('FILE_DENIED', $exception->getErrorCode());
        }

        $this->setActor($alice);
        $files->archive((string) $view['publicId']);
        $archived = $files->requireReadable((string) $view['publicId']);
        self::assertTrue($archived->isArchived());
        self::assertNotNull($archived->getArchivedAt());
        $capture = new ByteCapture();
        (new FileByteService($files, new FakeFileStorage(), $capture->emitter()))
            ->emit((string) $view['publicId'], null);
        self::assertSame(404, $capture->statusCode);
        self::assertSame('', $capture->body);
    }

    /**
     * Превышение общего лимита и чужой тип.
     *
     * @return void
     */
    public function testUploadLimits(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $tinyLimit = $this->uploadService(['path' => $this->storageRoot, 'max_size' => 4, 'mime' => ['image/png']]);
        try {
            $tinyLimit->upload($this->incoming(base64_decode(self::PNG_BASE64, true), 'cover.png'));
            self::fail('oversized upload must fail');
        } catch (FileTooLargeException $exception) {
            self::assertSame('FILE_TOO_LARGE', $exception->getErrorCode());
        }

        $imagesOnly = $this->uploadService([
            'path' => $this->storageRoot,
            'max_size' => 1000,
            'mime' => ['image/png'],
        ]);
        try {
            $imagesOnly->upload($this->incoming('plain text', 'note.txt'));
            self::fail('foreign mime must fail');
        } catch (FileMimeInvalidException $exception) {
            self::assertSame('FILE_MIME_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Производные байты: копия наружу, новая строка, проекция id → public_id.
     *
     * @return void
     */
    public function testDerivedCopiesAndProjection(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $this->setActor($alice);
        $section = ['path' => $this->storageRoot, 'max_size' => 1000, 'mime' => ['image/png']];
        $png = (string) base64_decode(self::PNG_BASE64, true);
        $sourceView = $this->uploadService($section)->upload($this->incoming($png, 'cover.png'));
        $sourcePublicId = (string) $sourceView['publicId'];
        $files = $this->filesService($section);

        $copyPath = $this->temporaryPath('opencode-derive-');
        $sourceRecord = $files->copyOwnedTo($sourcePublicId, $copyPath);
        self::assertSame($sourcePublicId, $sourceRecord->getPublicId());
        self::assertSame($png, (string) file_get_contents($copyPath));

        $derived = $files->storeDerived($sourcePublicId, $copyPath, 'avatar.webp');
        self::assertNotSame($sourceRecord->getId(), $derived->getId());
        self::assertSame($alice, $derived->getOwnerId());
        self::assertSame('avatar.webp', $derived->getOriginalName());
        self::assertSame('image/png', $derived->getMime());
        self::assertFalse($derived->isArchived());
        self::assertFileDoesNotExist($copyPath);

        $secondCopy = $this->temporaryPath('opencode-derive-');
        file_put_contents($secondCopy, $png);
        $second = $files->storeDerived($sourcePublicId, $secondCopy, '');
        self::assertSame('cover.png', $second->getOriginalName());

        self::assertSame([
            $sourceRecord->getId() => $sourceRecord->getPublicId(),
            $derived->getId() => $derived->getPublicId(),
            $second->getId() => $second->getPublicId(),
        ], $files->activePublicIds([
            $sourceRecord->getId(),
            $derived->getId(),
            $second->getId(),
            999999,
        ]));

        $files->archive($derived->getPublicId());
        self::assertSame([
            $sourceRecord->getId() => $sourceRecord->getPublicId(),
            $second->getId() => $second->getPublicId(),
        ], $files->activePublicIds([$sourceRecord->getId(), $derived->getId(), $second->getId()]));

        $this->setActor($bob);
        $foreignCopy = $this->temporaryPath('opencode-derive-');
        try {
            $files->copyOwnedTo($sourcePublicId, $foreignCopy);
            self::fail('foreign copy must be denied');
        } catch (FileDeniedException $exception) {
            self::assertSame('FILE_DENIED', $exception->getErrorCode());
        }

        try {
            $files->storeDerived($sourcePublicId, $foreignCopy, 'avatar.webp');
            self::fail('foreign derive must be denied');
        } catch (FileDeniedException $exception) {
            self::assertSame('FILE_DENIED', $exception->getErrorCode());
        }

        $this->setActor($alice);
        $files->archive($sourcePublicId);
        try {
            $files->copyOwnedTo($sourcePublicId, $foreignCopy);
            self::fail('archived copy must be denied');
        } catch (FileDeniedException $exception) {
            self::assertSame('FILE_DENIED', $exception->getErrorCode());
        }
    }

    /**
     * Подключение MySQL и портов.
     *
     * @return void
     */
    private function connectPorts(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $this->application = $application;
        $kernelContainer = $application->getLocator()->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;
        $userAccounts = $application->getLocator()->get(IUserContainer::class)->get(IUserAccounts::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        $this->userAccounts = $userAccounts;
        $userAccess = $application->getLocator()->get(IUserContainer::class)->get(IUserAccess::class);
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
            $gateway->open(UserAvatarTable::class)->schema(),
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
     * Сервис загрузки на временном корне.
     *
     * @param array<string, mixed> $section Срез files.
     *
     * @return FileUploadService Сервис.
     */
    private function uploadService(array $section): FileUploadService
    {
        $settings = FileSettings::fromSection($section, $this->storageRoot);

        return new FileUploadService(
            $this->userAccess(),
            new FileIntake(
                new FileRepository($this->smartTableGateway()->open(FileTable::class)->records()),
                (new FileStorageFactory())->create($settings),
                $settings,
            ),
            new FileViewAssembler(),
        );
    }

    /**
     * Порт файлов на временном корне теста.
     *
     * @param array<string, mixed> $section Срез files.
     *
     * @return IFiles Порт.
     */
    private function filesService(array $section): IFiles
    {
        $settings = FileSettings::fromSection($section, $this->storageRoot);
        $repository = new FileRepository($this->smartTableGateway()->open(FileTable::class)->records());
        $storage = (new FileStorageFactory())->create($settings);
        $userAccess = $this->userAccess();

        return new FilesService(
            new FileReadService($userAccess, $repository),
            $repository,
            $storage,
            new FileIntake($repository, $storage, $settings),
            $userAccess,
        );
    }

    /**
     * Создаёт временный файл и запоминает его для уборки.
     *
     * @param string $prefix Префикс.
     *
     * @return string Абсолютный путь.
     */
    private function temporaryPath(string $prefix): string
    {
        $temporaryPath = (string) tempnam(sys_get_temp_dir(), $prefix);
        $this->temporaryFiles[] = $temporaryPath;

        return $temporaryPath;
    }

    /**
     * Вход принятого файла из настоящего tmp.
     *
     * @param string $content Байты.
     * @param string $name Имя.
     *
     * @return IncomingUpload Вход.
     */
    private function incoming(string $content, string $name): IncomingUpload
    {
        $temporaryPath = (string) tempnam(sys_get_temp_dir(), 'opencode-http-upload-');
        file_put_contents($temporaryPath, $content);
        $this->temporaryFiles[] = $temporaryPath;

        return IncomingUpload::fromFileMap([
            'file' => [
                'tmp_name' => $temporaryPath,
                'name' => $name,
                'size' => strlen($content),
                'error' => UPLOAD_ERR_OK,
                'type' => 'application/octet-stream',
            ],
        ], strlen($content));
    }

    /**
     * Порт файлов.
     *
     * @return IFiles Порт.
     */
    private function files(): IFiles
    {
        $files = $this->application()->getLocator()->get(IFileContainer::class)->get(IFiles::class);
        self::assertInstanceOf(IFiles::class, $files);

        return $files;
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
     * Актор HTTP.
     *
     * @param int $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи.
     * @param bool $hasBypass Обход.
     *
     * @return void
     */
    private function setActor(int $userId, array $permissionKeys = [], bool $hasBypass = false): void
    {
        $this->requestContext()->setActor(new RequestActor($userId, $permissionKeys, $hasBypass));
    }

    /**
     * Action.
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
     * Приложение.
     *
     * @return IApplication Приложение.
     */
    private function application(): IApplication
    {
        self::assertInstanceOf(IApplication::class, $this->application);

        return $this->application;
    }

    /**
     * Контекст.
     *
     * @return IRequestContext Контекст.
     */
    private function requestContext(): IRequestContext
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);

        return $this->requestContext;
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

    /**
     * Рекурсивно удаляет временный корень.
     *
     * @param string $directoryPath Каталог.
     *
     * @return void
     */
    private function removeDirectory(string $directoryPath): void
    {
        if (!is_dir($directoryPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directoryPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($directoryPath);
    }
}
